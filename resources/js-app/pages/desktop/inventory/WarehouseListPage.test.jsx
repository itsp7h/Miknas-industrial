import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import WarehouseListPage from './WarehouseListPage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: {
        private: () => ({ listen: () => ({ listen: () => {} }), stopListening: () => {} }),
        channel: () => ({ listen: () => {}, stopListening: () => {} }),
        leave: () => {},
    },
}));

const WAREHOUSES = [
    { id: 1, code: 'WH-MAIN', name: 'Main Store', location: 'Sitra', latitude: 26.1421, longitude: 50.5832, is_active: true },
    // No pin: recorded before the map picker, or cleared since.
    { id: 2, code: 'WH-YARD', name: 'Yard', location: null, latitude: null, longitude: null, is_active: false },
];

// Each row links at the warehouse's own page now, so the tree needs a router.
const renderPage = () => render(
    <ToastProvider><MemoryRouter><WarehouseListPage /></MemoryRouter></ToastProvider>
);

describe('desktop WarehouseListPage', () => {
    beforeEach(() => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: WAREHOUSES });
    });

    it('lists warehouses from the API', async () => {
        renderPage();
        expect(await screen.findByText('Main Store')).toBeInTheDocument();
        expect(screen.getByText('WH-YARD')).toBeInTheDocument();
    });

    it('renders a placeholder rather than blank for a missing location', async () => {
        renderPage();
        await screen.findByText('Main Store');
        expect(screen.getByText('—')).toBeInTheDocument();
    });

    it('opens the create form', async () => {
        renderPage();
        await screen.findByText('Main Store');
        // Button label now matches the Blade header: "+ Add Warehouse".
        fireEvent.click(screen.getByText('+ Add Warehouse'));
        expect(await screen.findByLabelText('Code')).toBeInTheDocument();
    });

    it('opens the create form on a map, not a bare Location box', async () => {
        renderPage();
        await screen.findByText('Main Store');
        fireEvent.click(screen.getByText('+ Add Warehouse'));
        expect(await screen.findByTestId('warehouse-map')).toBeInTheDocument();
    });

    // A stored pin is worth nothing if no one can open it.
    it('links a pinned row at the map and leaves an unpinned one alone', async () => {
        renderPage();
        await screen.findByText('Main Store');

        const links = screen.getAllByText('📍 View on map');
        expect(links).toHaveLength(1);
        expect(links[0]).toHaveAttribute('href', expect.stringContaining('mlat=26.1421'));
        expect(links[0]).toHaveAttribute('target', '_blank');
    });

    /**
     * The Blade table badged status and used btn-sm row actions; the earlier
     * React table printed Yes/No with plain text links.
     */
    it('badges status and renders row actions as small buttons', async () => {
        renderPage();
        await screen.findByText('Main Store');
        expect(screen.getByText('Active')).toHaveClass('badge-green');
        expect(screen.queryByText('Yes')).not.toBeInTheDocument();
        expect(screen.getAllByText('Edit')[0]).toHaveClass('btn-secondary', 'btn-sm');
        expect(screen.getAllByText('Delete')[0]).toHaveClass('btn-danger', 'btn-sm');
    });

    it('shows the page header and its subtitle', async () => {
        renderPage();
        await screen.findByText('Main Store');
        expect(screen.getByText('Warehouses')).toHaveClass('page-title');
        expect(screen.getByText('Manage storage locations')).toHaveClass('page-subtitle');
    });

    it('filters client-side with a live count', async () => {
        renderPage();
        await screen.findByText('Main Store');
        const before = client.apiGet.mock.calls.length;

        fireEvent.change(screen.getByLabelText('Search warehouses'), { target: { value: 'zzz' } });

        expect(screen.getByText('No warehouses found.')).toBeInTheDocument();
        expect(client.apiGet.mock.calls.length).toBe(before);
    });

    it('explains when the API deactivates instead of deleting', async () => {
        vi.spyOn(client, 'apiDelete').mockResolvedValue({
            deactivated: true,
            message: 'Warehouse has stock records, so it was deactivated rather than deleted.',
        });
        renderPage();
        await screen.findByText('Main Store');
        fireEvent.click(screen.getAllByText('Delete')[0]);
        fireEvent.click(await screen.findByText('Confirm'));
        await waitFor(() => {
            expect(screen.getByText(/deactivated rather than deleted/)).toBeInTheDocument();
        });
    });
    /** Clicking a warehouse is how you see what is in it. */
    it('links each warehouse name at its own page', async () => {
        renderPage();
        const link = (await screen.findByText('Main Store')).closest('a');
        expect(link).toHaveAttribute('href', '/app/inventory/warehouses/1');
    });

});
