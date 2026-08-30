import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
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
    { id: 1, code: 'WH-MAIN', name: 'Main Store', location: 'Sitra', is_active: true },
    { id: 2, code: 'WH-YARD', name: 'Yard', location: null, is_active: false },
];

const renderPage = () => render(<ToastProvider><WarehouseListPage /></ToastProvider>);

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
        fireEvent.click(screen.getByText('New Warehouse'));
        expect(await screen.findByLabelText('Code')).toBeInTheDocument();
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
});
