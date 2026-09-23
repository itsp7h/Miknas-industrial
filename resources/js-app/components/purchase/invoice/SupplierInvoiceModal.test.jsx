import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import SupplierInvoiceModal from './SupplierInvoiceModal';
import * as client from '../../../api/client';

const OPTIONS = {
    suppliers: [{ id: 3, name: 'Gulf Metals' }],
    purchase_orders: [{ id: 5, po_number: 'PO-00005' }],
    grns: [{ id: 8, grn_number: 'GRN-00008' }],
    statuses: ['unpaid', 'partial', 'paid'],
};

describe('SupplierInvoiceModal', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue(OPTIONS);
    });

    const openCreate = async (props = {}) => {
        render(<SupplierInvoiceModal invoice={null} onSaved={() => {}} onCancel={() => {}} {...props} />);
        await screen.findByText('New Supplier Invoice');
    };

    it('opens with the create chrome and its four sections', async () => {
        await openCreate();

        ['Invoice Details', 'Linked Documents', 'Amounts', 'Notes'].forEach((section) => {
            expect(screen.getByRole('heading', { name: section })).toBeInTheDocument();
        });
        expect(screen.getByRole('button', { name: 'Save Invoice' })).toHaveClass('btn-primary');
    });

    /**
     * The completeness check: every field the store endpoint validates has
     * to be on the form and reach the payload. `status` is the exception —
     * the API marks it `prohibited` on create.
     */
    it('carries every field the API accepts on create', async () => {
        const post = vi.spyOn(client, 'apiPost').mockResolvedValue({ data: { id: 1 } });
        await openCreate();

        fireEvent.change(screen.getByLabelText(/Invoice Number/), { target: { value: 'INV-9' } });
        fireEvent.change(screen.getByLabelText(/Supplier/), { target: { value: '3' } });
        fireEvent.change(screen.getByLabelText(/Invoice Date/), { target: { value: '2026-09-01' } });
        fireEvent.change(screen.getByLabelText(/Due Date/), { target: { value: '2026-10-01' } });
        fireEvent.change(screen.getByLabelText(/Purchase Order/), { target: { value: '5' } });
        fireEvent.change(screen.getByLabelText(/Goods Receipt Note/), { target: { value: '8' } });
        fireEvent.change(screen.getByLabelText(/Subtotal/), { target: { value: '100' } });
        fireEvent.change(screen.getByLabelText(/VAT Amount/), { target: { value: '10' } });
        fireEvent.change(screen.getByLabelText('Notes'), { target: { value: 'Net 30' } });

        fireEvent.click(screen.getByRole('button', { name: 'Save Invoice' }));

        await waitFor(() => expect(post).toHaveBeenCalledWith('/purchase/invoices', {
            supplier_id: '3',
            invoice_number: 'INV-9',
            purchase_order_id: '5',
            goods_receipt_note_id: '8',
            invoice_date: '2026-09-01',
            due_date: '2026-10-01',
            subtotal: '100',
            vat_amount: '10',
            total_amount: 110,
            notes: 'Net 30',
        }));
    });

    it('computes the total as subtotal plus VAT, read-only', async () => {
        await openCreate();

        fireEvent.change(screen.getByLabelText(/Subtotal/), { target: { value: '100' } });
        fireEvent.change(screen.getByLabelText(/VAT Amount/), { target: { value: '10.5' } });

        expect(screen.getByText('BD 110.500')).toBeInTheDocument();
        expect(screen.queryByLabelText(/Total Amount/)).not.toBeInTheDocument();
    });

    /** The API marks status `prohibited` on create, so the field is absent there. */
    it('does not offer Status when creating', async () => {
        await openCreate();

        expect(screen.queryByLabelText('Status')).not.toBeInTheDocument();
    });

    it('offers Status when editing and sends it', async () => {
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({ data: { id: 4 } });
        const invoice = {
            id: 4, invoice_number: 'INV-4', supplier_id: 3, purchase_order_id: 5,
            goods_receipt_note_id: 8, invoice_date: '2026-09-01', due_date: '2026-10-01',
            subtotal: '100', vat_amount: '10', status: 'unpaid', notes: 'x',
        };
        render(<SupplierInvoiceModal invoice={invoice} onSaved={() => {}} onCancel={() => {}} />);
        await screen.findByText('Edit Supplier Invoice');

        expect(screen.getByText('INV-4')).toBeInTheDocument();
        fireEvent.change(screen.getByLabelText('Status'), { target: { value: 'paid' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save Changes' }));

        await waitFor(() => expect(put).toHaveBeenCalledWith('/purchase/invoices/4',
            expect.objectContaining({ status: 'paid', total_amount: 110 })));
    });

    /** Payments own paid_amount; the form must never send it. */
    it('never sends paid_amount', async () => {
        const post = vi.spyOn(client, 'apiPost').mockResolvedValue({ data: { id: 1 } });
        await openCreate();

        fireEvent.change(screen.getByLabelText(/Invoice Number/), { target: { value: 'INV-9' } });
        fireEvent.change(screen.getByLabelText(/Supplier/), { target: { value: '3' } });
        fireEvent.change(screen.getByLabelText(/Subtotal/), { target: { value: '5' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save Invoice' }));

        await waitFor(() => expect(post).toHaveBeenCalled());
        expect(post.mock.calls[0][1]).not.toHaveProperty('paid_amount');
    });

    it('shows a duplicate invoice number against its field and in the summary', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({
            errors: { invoice_number: ['The invoice number has already been taken.'] },
        });
        await openCreate();

        fireEvent.change(screen.getByLabelText(/Invoice Number/), { target: { value: 'INV-9' } });
        fireEvent.change(screen.getByLabelText(/Supplier/), { target: { value: '3' } });
        fireEvent.change(screen.getByLabelText(/Subtotal/), { target: { value: '5' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save Invoice' }));

        expect(await screen.findByRole('alert')).toHaveTextContent('already been taken');
        expect(screen.getAllByText('The invoice number has already been taken.')).toHaveLength(2);
    });

    it('reports a failure that carries no field errors', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({ message: 'Server unavailable.' });
        await openCreate();

        fireEvent.change(screen.getByLabelText(/Invoice Number/), { target: { value: 'INV-9' } });
        fireEvent.change(screen.getByLabelText(/Supplier/), { target: { value: '3' } });
        fireEvent.change(screen.getByLabelText(/Subtotal/), { target: { value: '5' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save Invoice' }));

        expect(await screen.findByRole('alert')).toHaveTextContent('Server unavailable.');
    });

    it('says so when the options cannot be loaded', async () => {
        vi.spyOn(client, 'apiGet').mockRejectedValue({ message: 'nope' });
        render(<SupplierInvoiceModal invoice={null} onSaved={() => {}} onCancel={() => {}} />);

        expect(await screen.findByRole('alert'))
            .toHaveTextContent('The supplier, order and GRN lists could not be loaded.');
    });
});
