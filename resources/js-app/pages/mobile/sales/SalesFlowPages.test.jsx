import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import { render, screen, fireEvent } from '@testing-library/react';
import DeliveryNoteListPage from './DeliveryNoteListPage';
import InvoiceListPage from './InvoiceListPage';
import PaymentListPage from './PaymentListPage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => ({ listen: () => {} }), stopListening: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const wrap = (ui) => render(<MemoryRouter><ToastProvider>{ui}</ToastProvider></MemoryRouter>);

const NOTES = [
    { id: 1, delivery_number: 'DN-00001', order_number: 'SO-00001', customer_name: 'Gulf Steel', warehouse_name: 'Main', delivery_date: '2026-08-05', status: 'draft' },
    { id: 2, delivery_number: 'DN-00002', order_number: 'SO-00002', customer_name: 'Zenith', warehouse_name: 'Yard', delivery_date: '2026-08-06', status: 'dispatched' },
];

describe('mobile sales flow pages', () => {
    beforeEach(() => vi.restoreAllMocks());

    it('renders delivery notes as cards with a live count', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: NOTES });
        const { container } = wrap(<DeliveryNoteListPage />);
        await screen.findByText('DN-00001');
        expect(container.querySelector('table')).toBeNull();
        expect(screen.getByText('2 notes')).toBeInTheDocument();
    });

    it('filters delivery notes client-side', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: NOTES });
        wrap(<DeliveryNoteListPage />);
        await screen.findByText('DN-00001');
        fireEvent.change(screen.getByLabelText('Search delivery notes'), { target: { value: 'zenith' } });
        expect(screen.getByText('1 of 2 notes')).toBeInTheDocument();
        expect(screen.queryByText('DN-00001')).not.toBeInTheDocument();
    });

    it('invoices show a due amount per card', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 1, invoice_number: 'INV-00001', customer_name: 'Gulf Steel', invoice_date: '2026-08-06', total_amount: '110.00', balance_due: 70, status: 'partial' }],
        });
        wrap(<InvoiceListPage />);
        await screen.findByText('INV-00001');
        expect(screen.getByText('70.00 due')).toBeInTheDocument();
    });

    it('payments say so plainly when there are none', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });
        wrap(<PaymentListPage />);
        expect(await screen.findByText('No payments recorded yet.')).toBeInTheDocument();
    });
});
