import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import StockMovementPage from './StockMovementPage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: {
        private: () => ({ listen: () => ({ listen: () => {} }), stopListening: () => {} }),
        channel: () => ({ listen: () => {}, stopListening: () => {} }),
        leave: () => {},
    },
}));

const MOVEMENTS = [
    { id: 1, item_name: 'Steel Rod', warehouse_name: 'Main', type: 'in', quantity: '10', notes: 'PO receipt', created_at: '2026-08-01T10:00:00+00:00' },
    { id: 2, item_name: 'Widget', warehouse_name: 'Yard', type: 'out', quantity: '3', notes: null, created_at: '2026-08-02T10:00:00+00:00' },
];

const renderPage = () => render(<ToastProvider><StockMovementPage /></ToastProvider>);

describe('desktop StockMovementPage', () => {
    beforeEach(() => {
        vi.spyOn(client, 'apiGet').mockImplementation((path) =>
            path === '/inventory/movements'
                ? Promise.resolve({ data: MOVEMENTS })
                : Promise.resolve({ items: [], warehouses: [], types: ['in', 'out'] })
        );
    });

    it('lists movements from the API', async () => {
        renderPage();
        expect(await screen.findByText('Steel Rod')).toBeInTheDocument();
        expect(screen.getByText('Yard')).toBeInTheDocument();
    });

    it('shows a placeholder rather than blank for empty notes', async () => {
        renderPage();
        await screen.findByText('Steel Rod');
        expect(screen.getByText('—')).toBeInTheDocument();
    });

    it('opens the adjustment form with item and warehouse pickers', async () => {
        renderPage();
        await screen.findByText('Steel Rod');
        fireEvent.click(screen.getByText('+ Manual Adjustment'));
        expect(await screen.findByLabelText('Item')).toBeInTheDocument();
        expect(screen.getByLabelText('Warehouse')).toBeInTheDocument();
        expect(screen.getByLabelText('Movement Type')).toBeInTheDocument();
    });
});
