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
    {
        id: 1, po_number: 'PO-00001', supplier_name: 'Gulf Metals', po_date: '2026-08-01',
        expected_delivery_date: '2026-08-10', total_amount: '600.00', status: 'draft',
    },
    {
        id: 2, po_number: 'PO-00002', supplier_name: 'Zenith Supply', po_date: '2026-08-02',
        expected_delivery_date: null, total_amount: '250.50', status: 'sent',
    },
];

const renderPage = () =>
    render(
        <ToastProvider>
            <MemoryRouter><PurchaseOrderListPage /></MemoryRouter>
        </ToastProvider>
    );

describe('desktop PurchaseOrderListPage', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockImplementation((path) =>
            path === '/purchase/orders'
                ? Promise.resolve({ data: ORDERS })
                : Promise.resolve({ suppliers: [], items: [], purchase_requests: [], statuses: [] })
        );
    });

    it('lists orders with money and status labels', async () => {
        renderPage();
        expect(await screen.findByText('PO-00001')).toBeInTheDocument();
        expect(screen.getByText('250.50')).toBeInTheDocument();
        expect(screen.getByText('Sent')).toBeInTheDocument();
    });

    // The Blade index rendered `d M Y`, not the raw ISO date.
    it('formats dates the way the Blade index did', async () => {
        renderPage();
        await screen.findByText('PO-00001');
        expect(screen.getByText('01 Aug 2026')).toBeInTheDocument();
    });

    it('shows an em dash where no delivery date is set', async () => {
        renderPage();
        await screen.findByText('PO-00002');
        expect(screen.getByText('—')).toBeInTheDocument();
    });

    it('fetches the full order before editing, since the list row has no line items', async () => {
        const spy = client.apiGet;
        renderPage();
        await screen.findByText('PO-00001');
        fireEvent.click(screen.getAllByText('Edit')[0]);
        await waitFor(() => expect(spy).toHaveBeenCalledWith('/purchase/orders/1'));
    });

    it('asks before deleting rather than deleting outright', async () => {
        renderPage();
        await screen.findByText('PO-00001');
        fireEvent.click(screen.getAllByText('Delete')[0]);
        expect(await screen.findByText(/will be permanently removed/)).toBeInTheDocument();
    });

    it('deletes through the API and drops the row', async () => {
        const del = vi.spyOn(client, 'apiDelete').mockResolvedValue({ deleted: true });
        renderPage();
        await screen.findByText('PO-00001');
        fireEvent.click(screen.getAllByText('Delete')[0]);
        fireEvent.click(await screen.findByRole('button', { name: 'Confirm' }));

        await waitFor(() => expect(del).toHaveBeenCalledWith('/purchase/orders/1'));
        await waitFor(() => expect(screen.queryByText('PO-00001')).not.toBeInTheDocument());
    });

    // The API refuses to delete an order that already has a GRN; that refusal
    // has to reach the user rather than vanishing.
    it('surfaces an API refusal as an error toast', async () => {
        vi.spyOn(client, 'apiDelete').mockRejectedValue({
            message: 'This order already has goods receipt notes and cannot be deleted.',
        });
        renderPage();
        await screen.findByText('PO-00001');
        fireEvent.click(screen.getAllByText('Delete')[0]);
        fireEvent.click(await screen.findByRole('button', { name: 'Confirm' }));

        await waitFor(() => {
            expect(screen.getByText(/already has goods receipt notes/)).toBeInTheDocument();
        });
    });
});
