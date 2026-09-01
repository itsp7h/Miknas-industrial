import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
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

const INVOICES = [
    { id: 1, invoice_number: 'INV-00001', customer_name: 'Gulf Steel', invoice_date: '2026-08-06', total_amount: '110.00', paid_amount: '40.00', balance_due: 70, status: 'partial' },
];

const RECEIPTS = [
    { id: 1, invoice_number: 'INV-00001', customer_name: 'Gulf Steel', receipt_date: '2026-08-07', amount: '40.00', payment_method: 'bank_transfer', reference_number: 'TT-9' },
];

describe('desktop sales flow pages', () => {
    beforeEach(() => vi.restoreAllMocks());

    it('delivery notes offer Dispatch only on a draft note', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: NOTES });
        wrap(<DeliveryNoteListPage />);
        await screen.findByText('DN-00001');
        expect(screen.getAllByText('Dispatch')).toHaveLength(1);
    });

    // Dispatch moves real stock and messages the customer, so it must be
    // explicit about being irreversible.
    it('warns that dispatching decrements stock and cannot be undone', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: NOTES });
        wrap(<DeliveryNoteListPage />);
        await screen.findByText('DN-00001');
        fireEvent.click(screen.getByText('Dispatch'));
        expect(await screen.findByText(/stock will be decremented at Main/)).toBeInTheDocument();
        expect(screen.getByText(/cannot be undone/)).toBeInTheDocument();
    });

    it('surfaces a refused dispatch as an error toast', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: NOTES });
        vi.spyOn(client, 'apiPatch').mockRejectedValue({ message: 'This delivery note has already been dispatched.' });
        wrap(<DeliveryNoteListPage />);
        await screen.findByText('DN-00001');
        fireEvent.click(screen.getByText('Dispatch'));
        fireEvent.click(await screen.findByText('Confirm'));
        await waitFor(() => {
            expect(screen.getByText('This delivery note has already been dispatched.')).toBeInTheDocument();
        });
    });

    it('invoices show the outstanding balance and status', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: INVOICES });
        wrap(<InvoiceListPage />);
        await screen.findByText('INV-00001');
        expect(screen.getByText('70.00')).toBeInTheDocument();
        expect(screen.getByText('Part Paid')).toBeInTheDocument();
    });

    it('payments label the method rather than showing the raw enum', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: RECEIPTS });
        wrap(<PaymentListPage />);
        await screen.findByText('INV-00001');
        expect(screen.getByText('Bank Transfer')).toBeInTheDocument();
        expect(screen.queryByText('bank_transfer')).not.toBeInTheDocument();
    });
});
