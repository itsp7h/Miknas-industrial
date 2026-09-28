import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import LocationMapModal from './LocationMapModal';
import { ToastProvider } from '../../ui/Toast';
import * as geocode from '../../map/geocode';


const PROJECT = { id: 3, name: 'Forkoll' };
const SAVED = {
    id: 9, name: 'Askar Yard', address: 'Askar', latitude: 26.06, longitude: 50.61, is_active: true,
    road: 'Road 45', block: '945', city: 'Askar', country: 'Bahrain',
};

const renderModal = (props = {}) => {
    const onSave = props.onSave ?? vi.fn().mockResolvedValue();
    const view = render(
        <ToastProvider>
            <LocationMapModal open project={PROJECT} location={null} onClose={() => {}} onSave={onSave} {...props} />
        </ToastProvider>
    );

    return { ...view, onSave };
};

describe('LocationMapModal — road, block, city and country', () => {
    beforeEach(() => vi.restoreAllMocks());

    it('fills the four fields from the place a search finds, and saves them', async () => {
        vi.spyOn(geocode, 'searchPlaces').mockResolvedValue([{ id: 'n1', label: 'Hidd', latitude: 26.24, longitude: 50.65 }]);
        const reverse = vi.spyOn(geocode, 'addressOfPoint').mockResolvedValue({
            road: 'Road 1', block: '115', city: 'Hidd', country: 'Bahrain', label: 'Road 1, Hidd, Bahrain',
        });
        const { onSave } = renderModal();

        fireEvent.change(screen.getByLabelText(/Location Name/), { target: { value: 'Hidd Yard' } });
        fireEvent.change(screen.getByLabelText('Search the map'), { target: { value: 'Hidd' } });
        fireEvent.click(screen.getByRole('button', { name: 'Find this place on the map' }));

        await waitFor(() => expect(screen.getByLabelText('City')).toHaveValue('Hidd'));
        expect(reverse).toHaveBeenCalledWith(26.24, 50.65);
        expect(screen.getByLabelText('Road / Street')).toHaveValue('Road 1');
        expect(screen.getByLabelText('Block')).toHaveValue('115');
        expect(screen.getByLabelText('Country')).toHaveValue('Bahrain');
        // The search is only a search: it stays as typed and is not saved.
        expect(screen.getByLabelText('Search the map')).toHaveValue('Hidd');

        fireEvent.change(screen.getByLabelText('City'), { target: { value: 'Hidd Port' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save Location' }));

        await waitFor(() => expect(onSave).toHaveBeenCalled());
        expect(onSave.mock.calls[0][2]).toMatchObject({
            name: 'Hidd Yard', road: 'Road 1', block: '115', city: 'Hidd Port', country: 'Bahrain',
            // The one-line address is the parts, not the search text.
            address: 'Road 1, Block 115, Hidd Port, Bahrain',
            latitude: 26.24, longitude: 50.65,
        });
    });

    it('keeps an address typed before the four fields, and offers it as the search', async () => {
        const { onSave } = renderModal({ location: { ...SAVED, address: 'Plot 12, Askar', road: null, block: null, city: null, country: null } });

        expect(screen.getByLabelText('Search the map')).toHaveValue('Plot 12, Askar');
        fireEvent.click(screen.getByRole('button', { name: 'Save Location' }));

        await waitFor(() => expect(onSave).toHaveBeenCalled());
        expect(onSave.mock.calls[0][2].address).toBe('Plot 12, Askar');
    });

    it('opens an existing location with its parts, and a list refresh does not wipe an edit', () => {
        const { rerender } = renderModal({ location: SAVED });

        expect(screen.getByLabelText('Road / Street')).toHaveValue('Road 45');
        expect(screen.getByLabelText('Block')).toHaveValue('945');
        fireEvent.change(screen.getByLabelText('City'), { target: { value: 'Sitra' } });

        // The page refetches after any write; the same location arrives as a new object.
        rerender(
            <ToastProvider>
                <LocationMapModal open project={PROJECT} location={{ ...SAVED }} onClose={() => {}} onSave={vi.fn()} />
            </ToastProvider>
        );
        expect(screen.getByLabelText('City')).toHaveValue('Sitra');
    });

    it('sends a part left blank as null', async () => {
        const { onSave } = renderModal({ location: { ...SAVED, road: null, block: null } });

        fireEvent.click(screen.getByRole('button', { name: 'Save Location' }));

        await waitFor(() => expect(onSave).toHaveBeenCalled());
        expect(onSave.mock.calls[0][2]).toMatchObject({ road: null, block: null, city: 'Askar', country: 'Bahrain', address: 'Askar, Bahrain' });
    });

    it('leaves the fields alone when the lookup finds no address', async () => {
        vi.spyOn(geocode, 'searchPlaces').mockResolvedValue([{ id: 'n2', label: 'Gulf', latitude: 26.5, longitude: 50.9 }]);
        vi.spyOn(geocode, 'addressOfPoint').mockResolvedValue(null);
        renderModal({ location: { ...SAVED, road: null, block: null } });

        fireEvent.change(screen.getByLabelText('Search the map'), { target: { value: 'somewhere at sea' } });
        fireEvent.click(screen.getByRole('button', { name: 'Find this place on the map' }));

        await waitFor(() => expect(geocode.addressOfPoint).toHaveBeenCalled());
        expect(screen.getByLabelText('City')).toHaveValue('Askar');
    });

    it('searches the address in the fields, with the search box as the city', async () => {
        const find = vi.spyOn(geocode, 'findAddress').mockResolvedValue({ latitude: 26.2148, longitude: 50.586, matched: 'road' });
        const places = vi.spyOn(geocode, 'searchPlaces');
        const reverse = vi.spyOn(geocode, 'addressOfPoint');
        renderModal();

        fireEvent.change(screen.getByLabelText('Search the map'), { target: { value: 'Manama' } });
        fireEvent.change(screen.getByLabelText('Road / Street'), { target: { value: 'Road 2734' } });
        fireEvent.change(screen.getByLabelText('Block'), { target: { value: '338' } });
        fireEvent.click(screen.getByRole('button', { name: 'Find this place on the map' }));

        await waitFor(() => expect(screen.getByLabelText('Latitude')).toHaveValue(26.2148));
        expect(screen.getByLabelText('Longitude')).toHaveValue(50.586);
        expect(find).toHaveBeenCalledWith(expect.objectContaining({ road: 'Road 2734', block: '338', city: 'Manama' }));
        expect(places).not.toHaveBeenCalled();
        // The search box's city lands in City; nothing typed is overwritten.
        expect(screen.getByLabelText('City')).toHaveValue('Manama');
        expect(screen.getByLabelText('Road / Street')).toHaveValue('Road 2734');
        expect(reverse).not.toHaveBeenCalled();
    });

    it('moves nothing when a field is merely left — only the search button searches', () => {
        const find = vi.spyOn(geocode, 'findAddress');
        renderModal({ location: SAVED });

        const road = screen.getByLabelText('Road / Street');
        fireEvent.focus(road);
        fireEvent.change(road, { target: { value: 'Road 2734' } });
        fireEvent.blur(road);

        expect(find).not.toHaveBeenCalled();
    });

    it('searches when Enter is pressed in an address field', async () => {
        const find = vi.spyOn(geocode, 'findAddress').mockResolvedValue(null);
        renderModal({ location: SAVED });

        fireEvent.keyDown(screen.getByLabelText('Road / Street'), { key: 'Enter' });

        await waitFor(() => expect(find).toHaveBeenCalledWith(expect.objectContaining({ road: 'Road 45', city: 'Askar' })));
    });

    it('says so when the road is not on the map and the pin fell back to the block', async () => {
        vi.spyOn(geocode, 'findAddress').mockResolvedValue({ latitude: 26.212, longitude: 50.5923, matched: 'block' });
        renderModal();

        fireEvent.change(screen.getByLabelText('Search the map'), { target: { value: 'Manama' } });
        fireEvent.change(screen.getByLabelText('Road / Street'), { target: { value: 'Road 17' } });
        fireEvent.change(screen.getByLabelText('Block'), { target: { value: '338' } });
        fireEvent.click(screen.getByRole('button', { name: 'Find this place on the map' }));

        expect(await screen.findByText('Road 17 is not on the map — the pin is at block 338. Drag it to the exact spot.')).toBeInTheDocument();
        expect(screen.getByLabelText('Latitude')).toHaveValue(26.212);
    });
});
