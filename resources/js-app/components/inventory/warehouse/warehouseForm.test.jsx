import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import WarehouseForm from './WarehouseForm';
import * as client from '../../../api/client';
import * as geocode from '../../map/geocode';

// Nominatim is the one thing here that would leave the machine. Leaflet itself
// runs fine under jsdom — it just never paints a tile — so the map is the real
// component and only the lookups are stood in for.
vi.mock('../../map/geocode', () => ({
    searchPlaces: vi.fn(),
    describePoint: vi.fn(),
}));

const renderForm = (warehouse = null, onSaved = vi.fn()) =>
    render(<WarehouseForm warehouse={warehouse} onSaved={onSaved} onCancel={vi.fn()} />);

const fill = (label, value) => fireEvent.change(screen.getByLabelText(label), { target: { value } });

describe('WarehouseForm location picker', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        geocode.describePoint.mockResolvedValue('');
        vi.spyOn(client, 'apiPost').mockResolvedValue({ data: { id: 9 } });
        vi.spyOn(client, 'apiPut').mockResolvedValue({ data: { id: 9 } });
    });

    it('offers a map to pick the location on, not just a text box', () => {
        renderForm();
        expect(screen.getByTestId('warehouse-map')).toBeInTheDocument();
        expect(screen.getByLabelText('Search for a place')).toBeInTheDocument();
        expect(screen.getByText('Click the map to drop a pin, or search for a place above.')).toBeInTheDocument();
    });

    it('sends the picked coordinates with the warehouse', async () => {
        renderForm();
        fill('Code', 'WH-HIDD');
        fill('Name', 'Hidd Store');
        fill('Latitude', '26.1421');
        fill('Longitude', '50.5832');

        fireEvent.click(screen.getByText('Create Warehouse'));

        await waitFor(() => expect(client.apiPost).toHaveBeenCalled());
        expect(client.apiPost.mock.calls[0][1]).toMatchObject({
            code: 'WH-HIDD', latitude: 26.1421, longitude: 50.5832,
        });
    });

    it('reports where the pin is once one is down', () => {
        renderForm();
        fill('Latitude', '26.1421');
        fill('Longitude', '50.5832');
        expect(screen.getByText(/Pin at 26\.14210, 50\.58320/)).toBeInTheDocument();
    });

    /**
     * Searching is a round-trip to Nominatim, so it happens on a deliberate
     * Search press — never per keystroke, which its usage policy forbids.
     */
    it('looks a place up only when asked, and drops the pin on the result', async () => {
        geocode.searchPlaces.mockResolvedValue([
            { id: 'w1', label: 'Hidd Industrial Area, Muharraq, Bahrain', latitude: 26.1421, longitude: 50.5832 },
        ]);
        renderForm();

        fireEvent.change(screen.getByLabelText('Search for a place'), { target: { value: 'Hidd' } });
        expect(geocode.searchPlaces).not.toHaveBeenCalled();

        fireEvent.click(screen.getByText('Search'));
        fireEvent.click(await screen.findByText('Hidd Industrial Area, Muharraq, Bahrain'));

        expect(screen.getByLabelText('Latitude')).toHaveValue(26.1421);
        // The result's own label is the address, with no second lookup for it.
        expect(screen.getByLabelText('Address')).toHaveValue('Hidd Industrial Area, Muharraq, Bahrain');
        expect(geocode.describePoint).not.toHaveBeenCalled();
    });

    /**
     * Enter inside a form submits it. In the place search it has to search, or
     * looking up an address saves a half-filled warehouse instead.
     */
    it('searches on Enter rather than submitting the form', async () => {
        geocode.searchPlaces.mockResolvedValue([]);
        renderForm();

        fireEvent.keyDown(screen.getByLabelText('Search for a place'), { key: 'Enter' });
        expect(client.apiPost).not.toHaveBeenCalled();

        fireEvent.change(screen.getByLabelText('Search for a place'), { target: { value: 'Askar' } });
        fireEvent.keyDown(screen.getByLabelText('Search for a place'), { key: 'Enter' });
        await waitFor(() => expect(geocode.searchPlaces).toHaveBeenCalledWith('Askar'));
        expect(client.apiPost).not.toHaveBeenCalled();
    });

    it('says so when a place cannot be found', async () => {
        geocode.searchPlaces.mockResolvedValue([]);
        renderForm();
        fireEvent.change(screen.getByLabelText('Search for a place'), { target: { value: 'nowhere at all' } });
        fireEvent.click(screen.getByText('Search'));

        expect(await screen.findByText(/Nothing found for that/)).toBeInTheDocument();
    });

    // The map needs tiles from the internet. A browser on the LAN without any
    // sees a grey box, and the coordinate boxes are what keep the form usable.
    it('surfaces a map-service failure without blocking the form', async () => {
        geocode.searchPlaces.mockRejectedValue(new Error('The map service is not answering. Drop the pin by hand, or try again.'));
        renderForm();
        fireEvent.change(screen.getByLabelText('Search for a place'), { target: { value: 'Askar' } });
        fireEvent.click(screen.getByText('Search'));

        expect(await screen.findByText(/map service is not answering/)).toBeInTheDocument();
        expect(screen.getByLabelText('Latitude')).toBeEnabled();
    });

    it('puts the pin back where an edited warehouse left it', () => {
        renderForm({ id: 4, code: 'WH-HIDD', name: 'Hidd', location: 'Hidd', latitude: 26.1421, longitude: 50.5832, is_active: true });
        expect(screen.getByLabelText('Latitude')).toHaveValue(26.1421);
        expect(screen.getByLabelText('Longitude')).toHaveValue(50.5832);
    });

    it('clears the pin without touching the address the user typed', () => {
        renderForm({ id: 4, code: 'WH-HIDD', name: 'Hidd', location: 'Yard 3, Gate B', latitude: 26.1421, longitude: 50.5832, is_active: true });

        fireEvent.click(screen.getByText('Clear pin'));

        expect(screen.getByLabelText('Latitude')).toHaveValue(null);
        expect(screen.getByLabelText('Address')).toHaveValue('Yard 3, Gate B');
        expect(screen.getByText('Click the map to drop a pin, or search for a place above.')).toBeInTheDocument();
    });

    // Clearing one box must leave the other alone — both used to read from a
    // single "do we have a complete pair" flag and blank together.
    it('keeps the longitude when the latitude is cleared', () => {
        renderForm({ id: 4, code: 'WH', name: 'W', location: '', latitude: 26.1421, longitude: 50.5832, is_active: true });
        fill('Latitude', '');
        expect(screen.getByLabelText('Longitude')).toHaveValue(50.5832);
    });

    it('shows the validation error the API returns for a bad pin', async () => {
        client.apiPost.mockRejectedValue({ errors: { latitude: ['The latitude field must be between -90 and 90.'] } });
        renderForm();
        fill('Code', 'WH-X');
        fill('Name', 'X');
        fireEvent.click(screen.getByText('Create Warehouse'));

        expect(await screen.findByText('The latitude field must be between -90 and 90.')).toBeInTheDocument();
    });
});
