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
    { id: 1, delivery_number: 'DN-00001', sales_order_id: 11, order_number: 'SO-00001', customer_name: 'Gulf Steel', warehouse_id: 1, warehouse_name: 'Main', delivery_date: '2026-08-05', status: 'draft', notes: null },
    { id: 2, delivery_number: 'DN-00002', sales_order_id: 12, order_number: 'SO-00002', customer_name: 'Zenith', warehouse_name: 'Yard', delivery_date: '2026-08-06', status: 'dispatched' },
];

const INVOICES = [
    { id: 1, invoice_number: 'INV-00001', customer_name: 'Gulf Steel', sales_order_id: 11, order_number: 'SO-00001', invoice_date: '2026-08-06', due_date: null, subtotal: '100.00', vat_rate: 10, total_amount: '110.00', paid_amount: '40.00', balance_due: 70, status: 'partial' },
    { id: 2, invoice_number: 'INV-00002', customer_name: 'Zenith', sales_order_id: 12, order_number: 'SO-00002', invoice_date: '2026-08-07', due_date: null, subtotal: '50.00', vat_rate: 0, total_amount: '50.00', paid_amount: '0.00', balance_due: 50, status: 'unpaid' },
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

    // Blade's seven columns, with the date as `d M Y` and badged statuses.
    it('delivery notes list Blade\u2019s seven columns', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: NOTES });
        wrap(<DeliveryNoteListPage />);

        await screen.findByText('DN-00001');
        expect(screen.getAllByRole('columnheader').map((th) => th.textContent))
            .toEqual(['DN #', 'Sales Order', 'Customer', 'Warehouse', 'Date', 'Status', 'Actions']);
        expect(screen.getByText('05 Aug 2026')).toBeInTheDocument();
        // The status enum is draft/dispatched; Blade badged 'pending', which the
        // column cannot hold, so a real draft note badged grey.
        expect(screen.getByText('Draft')).toHaveClass('badge-yellow');
        expect(screen.getByText('Dispatched')).toHaveClass('badge-green');
        expect(screen.getByText('SO-00001')).toHaveAttribute('href', '/app/sales/orders/11');
    });

    // Blade offered both on every note; the React port offered neither.
    it('delivery notes offer Edit and Delete on a draft note only', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: NOTES });
        wrap(<DeliveryNoteListPage />);

        await screen.findByText('DN-00001');
        expect(screen.getAllByText('Edit')).toHaveLength(1);
        expect(screen.getAllByText('Delete')).toHaveLength(1);
        expect(screen.getByText('Edit')).toHaveClass('btn-secondary');
        expect(screen.getByText('Dispatch')).toHaveClass('btn-success');
    });

    it('delivery notes edit warehouse, date and notes', async () => {
        vi.spyOn(client, 'apiGet').mockImplementation((url) => (
            url.endsWith('/form-options')
                ? Promise.resolve({ orders: [], warehouses: [{ id: 1, name: 'Main' }] })
                : Promise.resolve({ data: NOTES })
        ));
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({ data: { ...NOTES[0], notes: 'Gate 3' } });
        wrap(<DeliveryNoteListPage />);

        await screen.findByText('DN-00001');
        fireEvent.click(screen.getByText('Edit'));
        // Blade's own update dropped the notes field its form posted.
        fireEvent.change(await screen.findByLabelText('Notes'), { target: { value: 'Gate 3' } });
        fireEvent.click(screen.getByText('Update Delivery Note'));

        await waitFor(() => expect(put).toHaveBeenCalledWith('/sales/delivery-notes/1', expect.objectContaining({ notes: 'Gate 3' })));
    });

    it('delivery notes say so when there are none', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });
        wrap(<DeliveryNoteListPage />);
        expect(await screen.findByText('No delivery notes found.')).toBeInTheDocument();
    });

    it('invoices show the outstanding balance and status', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: INVOICES });
        wrap(<InvoiceListPage />);
        await screen.findByText('INV-00001');
        expect(screen.getByText('70.00')).toBeInTheDocument();
        expect(screen.getByText('Part Paid')).toBeInTheDocument();
    });

    // Blade's nine columns. The port had seven and no SO # at all.
    it('invoices list Blade\u2019s nine columns', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: INVOICES });
        wrap(<InvoiceListPage />);

        await screen.findByText('INV-00001');
        expect(screen.getAllByRole('columnheader').map((th) => th.textContent)).toEqual([
            'Invoice #', 'Customer', 'SO #', 'Date', 'Total', 'Paid', 'Outstanding', 'Status', 'Actions',
        ]);
        expect(screen.getByText('SO-00001')).toHaveAttribute('href', '/app/sales/orders/11');
        expect(screen.getByText('06 Aug 2026')).toBeInTheDocument();
        expect(screen.getByText('Part Paid')).toHaveClass('badge-yellow');
        expect(screen.getByText('Unpaid')).toHaveClass('badge-red');
        expect(screen.getByText('70.00')).toHaveClass('text-red-600', 'font-semibold');
        expect(screen.getByText('40.00')).toHaveClass('text-green-700');
    });

    // The port had no actions on this page at all — no way to receive a payment,
    // edit or delete.
    it('invoices offer Receive and Edit, and Delete only before any money lands', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: INVOICES });
        wrap(<InvoiceListPage />);

        await screen.findByText('INV-00001');
        expect(screen.getAllByText('Receive')).toHaveLength(2);
        expect(screen.getAllByText('Edit')).toHaveLength(2);
        // INV-00001 has 40.00 against it; only the untouched invoice can go.
        expect(screen.getAllByText('Delete')).toHaveLength(1);
        expect(screen.getAllByText('Receive')[0]).toHaveAttribute('href', '/app/sales/payments?invoice_id=1');
    });

    // Blade's edit form offered a free-text Status; setting it made an invoice
    // read "paid" with no receipts behind it.
    it('the edit form has no status field and locks amounts once money is received', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: INVOICES });
        wrap(<InvoiceListPage />);

        await screen.findByText('INV-00001');
        fireEvent.click(screen.getAllByText('Edit')[0]);

        expect(await screen.findByLabelText(/Invoice Date/)).toBeInTheDocument();
        expect(screen.queryByLabelText('Status')).not.toBeInTheDocument();
        expect(screen.getByLabelText('Subtotal')).toBeDisabled();
        expect(screen.getByText(/its amounts are fixed/)).toBeInTheDocument();
    });

    it('the edit form leaves amounts editable while nothing has been received', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: INVOICES });
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({ data: INVOICES[1] });
        wrap(<InvoiceListPage />);

        await screen.findByText('INV-00002');
        fireEvent.click(screen.getAllByText('Edit')[1]);
        fireEvent.change(await screen.findByLabelText('Subtotal'), { target: { value: '80' } });
        fireEvent.click(screen.getByText('Update Invoice'));

        await waitFor(() => expect(put).toHaveBeenCalledWith('/sales/invoices/2', expect.objectContaining({ subtotal: '80' })));
    });

    it('invoices say so when there are none', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });
        wrap(<InvoiceListPage />);
        expect(await screen.findByText('No invoices found.')).toBeInTheDocument();
    });

    it('payments label the method rather than showing the raw enum', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: RECEIPTS });
        wrap(<PaymentListPage />);
        await screen.findByText('INV-00001');
        expect(screen.getByText('Bank Transfer')).toBeInTheDocument();
        expect(screen.queryByText('bank_transfer')).not.toBeInTheDocument();
    });
});
