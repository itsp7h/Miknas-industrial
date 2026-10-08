import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import StockMovementPage from './StockMovementPage';
import { ToastProvider } from '../../../components/ui/Toast';
import { AccessProvider } from '../../../layouts/AccessContext';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: {
        private: () => ({ listen: () => ({ listen: () => {} }), stopListening: () => {} }),
        channel: () => ({ listen: () => {}, stopListening: () => {} }),
        leave: () => {},
    },
}));

const MOVEMENTS = [
    { id: 1, item_id: 1, item_code: 'ITEM-1', item_name: 'Steel Rod', warehouse_name: 'Main', type: 'in', quantity: '10', notes: 'PO receipt', reference: 'Goods Receipt #4', created_at: '2026-08-01T10:00:00' },
    { id: 2, item_id: 2, item_code: 'ITEM-2', item_name: 'Widget', warehouse_name: 'Yard', type: 'out', quantity: '3', notes: null, reference: null, created_at: '2026-08-02T10:00:00' },
];

// The item filter's options repeat each name; these look at the rows.
const ROW = { selector: 'span' };

const renderPage = (permissions = null) => render(
    <ToastProvider>
        <AccessProvider isAdmin={permissions === null} permissions={permissions ?? []}>
            <MemoryRouter><StockMovementPage /></MemoryRouter>
        </AccessProvider>
    </ToastProvider>
);

describe('mobile StockMovementPage', () => {
    beforeEach(() => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: MOVEMENTS });
    });

    it('groups movements by day, signed in green and out red', async () => {
        const { container } = renderPage();
        expect(await screen.findByText('Steel Rod', ROW)).toBeInTheDocument();
        expect(container.querySelector('table')).toBeNull();
        expect(screen.getByText('01 Aug 2026')).toBeInTheDocument();
        expect(screen.getByText('02 Aug 2026')).toBeInTheDocument();
        expect(screen.getByText('+10.00')).toBeInTheDocument();
        expect(screen.getByText('−3.00')).toBeInTheDocument();
        expect(screen.getByText(/Manual adjustment/)).toBeInTheDocument();
    });

    it('counts and filters by direction', async () => {
        renderPage();
        await screen.findByText('Steel Rod', ROW);
        fireEvent.click(screen.getByText('Stock out · 1'));
        expect(screen.queryByText('Steel Rod', ROW)).not.toBeInTheDocument();
        expect(screen.getByText('Widget', ROW)).toBeInTheDocument();
    });

    it('filters by item and searches client-side, with no refetch', async () => {
        renderPage();
        await screen.findByText('Steel Rod', ROW);
        const before = client.apiGet.mock.calls.length;

        fireEvent.change(screen.getByLabelText('Filter by item'), { target: { value: '2' } });
        expect(screen.queryByText('Steel Rod', ROW)).not.toBeInTheDocument();
        expect(screen.getByText('All · 1')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Filter by item'), { target: { value: '' } });
        fireEvent.change(screen.getByLabelText('Search movements'), { target: { value: 'zzz' } });
        expect(screen.getByText('No movements match')).toBeInTheDocument();
        expect(client.apiGet.mock.calls.length).toBe(before);
    });

    // CLAUDE.md #14.
    it('offers the adjustment disabled without stock-movements.create', async () => {
        renderPage(['stock-movements.view']);
        await screen.findByText('Steel Rod', ROW);
        const button = screen.getByRole('button', { name: 'Manual adjustment' });
        expect(button).toBeDisabled();
        expect(button).toHaveAttribute('title', 'You do not have permission to adjust stock');
    });
});
