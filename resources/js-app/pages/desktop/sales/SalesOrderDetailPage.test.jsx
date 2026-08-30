import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import SalesOrderDetailPage from './SalesOrderDetailPage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => ({ listen: () => {} }) }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const ORDER = {
    id: 1, order_number: 'SO-00001', customer_name: 'Gulf Steel', order_date: '2026-08-01',
    delivery_date: '2026-08-10', total_amount: '100.00', status: 'confirmed', notes: 'Deliver to gate 3',
    items: [{ id: 5, item_name: 'Widget', quantity: '2.00', price: '50.00', total_amount: '100.00', quantity_delivered: '1.00' }],
    delivery_notes: [{ id: 9, delivery_number: 'DN-1', delivery_date: '2026-08-05', status: 'dispatched' }],
    invoices: [{ id: 3, invoice_number: 'INV-1', invoice_date: '2026-08-06', total_amount: '100.00', status: 'unpaid' }],
};

function renderAt(id, payload = { data: ORDER }) {
    vi.spyOn(client, 'apiGet').mockResolvedValue(payload);
    return render(
        <ToastProvider>
            <MemoryRouter initialEntries={[`/app/sales/orders/${id}`]}>
                <Routes>
                    <Route path="/app/sales/orders/:id" element={<SalesOrderDetailPage />} />
                </Routes>
            </MemoryRouter>
        </ToastProvider>
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
        expect(screen.getByText(/delivered 1.00/)).toBeInTheDocument();
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
