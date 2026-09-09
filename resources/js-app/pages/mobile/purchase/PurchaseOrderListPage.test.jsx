import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import PurchaseOrderListPage from './PurchaseOrderListPage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => ({ listen: () => {} }), stopListening: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const ORDERS = [
    { id: 1, po_number: 'PO-00001', supplier_name: 'Gulf Metals', po_date: '2026-08-01', total_amount: '600.00', status: 'draft' },
    { id: 2, po_number: 'PO-00002', supplier_name: 'Zenith Supply', po_date: '2026-08-02', total_amount: '250.50', status: 'sent' },
];

const renderPage = () =>
    render(
        <ToastProvider>
            <MemoryRouter><PurchaseOrderListPage /></MemoryRouter>
        </ToastProvider>
    );

describe('mobile PurchaseOrderListPage', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockImplementation((path) =>
            path === '/purchase/orders'
                ? Promise.resolve({ data: ORDERS })
                : Promise.resolve({ suppliers: [], items: [], purchase_requests: [], statuses: [] })
        );
    });

    it('renders each order as a card rather than a wide table', async () => {
        renderPage();
        expect(await screen.findByText('PO-00001')).toBeInTheDocument();
        expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });

    // CLAUDE.md gotcha #6: search filters client-side over the full list, with a
    // live count and no request.
    it('filters client-side and shows a live count', async () => {
        renderPage();
        await screen.findByText('PO-00001');
        expect(screen.getByText('2 orders')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Search purchase orders'), { target: { value: 'zenith' } });

        expect(screen.getByText('1 of 2 orders')).toBeInTheDocument();
        expect(screen.queryByText('PO-00001')).not.toBeInTheDocument();
        expect(screen.getByText('PO-00002')).toBeInTheDocument();
    });

    it('says so when a search matches nothing', async () => {
        renderPage();
        await screen.findByText('PO-00001');
        fireEvent.change(screen.getByLabelText('Search purchase orders'), { target: { value: 'nope' } });
        expect(screen.getByText('No purchase orders match that search.')).toBeInTheDocument();
    });

    it('deletes through the API after confirming', async () => {
        const del = vi.spyOn(client, 'apiDelete').mockResolvedValue({ deleted: true });
        renderPage();
        await screen.findByText('PO-00001');
        fireEvent.click(screen.getAllByText('Delete')[0]);
        fireEvent.click(await screen.findByRole('button', { name: 'Confirm' }));

        await waitFor(() => expect(del).toHaveBeenCalledWith('/purchase/orders/1'));
    });
});
