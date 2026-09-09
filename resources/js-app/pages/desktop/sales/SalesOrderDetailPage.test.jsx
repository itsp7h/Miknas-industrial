import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import SalesOrderDetailPage from './SalesOrderDetailPage';
import { ToastProvider } from '../../../components/ui/Toast';
import { PageTitleProvider } from '../../../layouts/PageTitleContext';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => {}, stopListening: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const ORDER = {
    id: 1, order_number: 'SO-00001', customer_name: 'Gulf Steel', order_date: '2026-08-01',
    delivery_date: '2026-08-10', total_amount: '100.00', status: 'confirmed', notes: 'Deliver to gate 3',
    customer: { id: 4, name: 'Gulf Steel', contact_person: 'A. Buyer', email: 'a@gulf.example', phone: '111' },
    items: [{ id: 5, item_name: 'Widget', quantity: '2.00', price: '50.00', total_amount: '100.00', quantity_delivered: '1.00' }],
    delivery_notes: [{ id: 9, delivery_number: 'DN-1', warehouse_name: 'Main', delivery_date: '2026-08-05', status: 'dispatched' }],
    invoices: [{ id: 3, invoice_number: 'INV-1', invoice_date: '2026-08-06', total_amount: '100.00', status: 'unpaid' }],
};

function renderAt(id, payload = { data: ORDER }) {
    vi.spyOn(client, 'apiGet').mockResolvedValue(payload);
    return render(
        <PageTitleProvider>
            <ToastProvider>
                <MemoryRouter initialEntries={[`/app/sales/orders/${id}`]}>
                    <Routes>
                        <Route path="/app/sales/orders/:id" element={<SalesOrderDetailPage />} />
                    </Routes>
                </MemoryRouter>
            </ToastProvider>
        </PageTitleProvider>
    );
}

describe('desktop SalesOrderDetailPage', () => {
    beforeEach(() => vi.restoreAllMocks());

    it('shows the order header, customer and status', async () => {
        renderAt(1);
        expect(await screen.findByText('SO-00001')).toBeInTheDocument();
        expect(screen.getByText('Gulf Steel')).toBeInTheDocument();
        expect(screen.getByText('Confirmed')).toBeInTheDocument();
    });

    it('lists line items with delivered quantities', async () => {
        renderAt(1);
        expect(await screen.findByText('Widget')).toBeInTheDocument();
        expect(screen.getByText(/1.00 delivered/)).toBeInTheDocument();
    });

    // Blade put the customer's contact details in their own card beside the
    // order; the port dropped the card and the resource never sent the fields.
    it('shows the customer card Blade had', async () => {
        renderAt(1);
        expect(await screen.findByText('Customer')).toBeInTheDocument();
        expect(screen.getByText('A. Buyer')).toBeInTheDocument();
        expect(screen.getByText('a@gulf.example')).toBeInTheDocument();
        expect(screen.getByText('111')).toBeInTheDocument();
    });

    it('totals the line items in a footer row', async () => {
        const { container } = renderAt(1);
        await screen.findByText('Widget');
        const footer = container.querySelector('tfoot');
        expect(footer).toHaveTextContent('Total');
        expect(footer).toHaveTextContent('100.00');
    });

    // The page had no actions at all: no way to confirm, edit, or raise a
    // delivery note, all of which Blade offered in its header.
    it('offers Create Delivery Note on a confirmed order', async () => {
        renderAt(1);
        expect(await screen.findByText('Create Delivery Note'))
            .toHaveAttribute('href', '/app/sales/delivery-notes?sales_order_id=1');
        expect(screen.queryByText('Confirm Order')).not.toBeInTheDocument();
    });

    it('confirms a draft order after asking', async () => {
        const patch = vi.spyOn(client, 'apiPatch').mockResolvedValue({ data: ORDER });
        renderAt(1, { data: { ...ORDER, status: 'draft' } });

        fireEvent.click(await screen.findByText('Confirm Order'));
        expect(await screen.findByText(/can no longer be edited/)).toBeInTheDocument();
        fireEvent.click(screen.getByText('Confirm'));
        await waitFor(() => expect(patch).toHaveBeenCalledWith('/sales/orders/1/confirm'));
    });

    // The Blade show page listed these; the detail page must not lose them.
    it('lists related delivery notes and invoices', async () => {
        renderAt(1);
        expect(await screen.findByText(/DN-1/)).toBeInTheDocument();
        expect(screen.getByText(/INV-1/)).toBeInTheDocument();
    });

    it('says so plainly when nothing has been dispatched or invoiced', async () => {
        renderAt(1, { data: { ...ORDER, delivery_notes: [], invoices: [] } });
        expect(await screen.findByText('Nothing dispatched yet.')).toBeInTheDocument();
        expect(screen.getByText('Not invoiced yet.')).toBeInTheDocument();
    });
});
