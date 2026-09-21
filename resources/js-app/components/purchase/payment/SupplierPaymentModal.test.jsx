import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import SupplierPaymentModal from './SupplierPaymentModal';
import * as client from '../../../api/client';

const OPTIONS = {
    invoices: [
        { id: 4, invoice_number: 'INV-4', supplier_name: 'Gulf Metals', outstanding: 250 },
        { id: 6, invoice_number: 'INV-6', supplier_name: 'Delta Steel', outstanding: 80 },
    ],
    methods: ['cash', 'bank_transfer', 'cheque', 'other'],
};

describe('SupplierPaymentModal', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue(OPTIONS);
    });

    const openCreate = async (props = {}) => {
        render(<SupplierPaymentModal payment={null} onSaved={() => {}} onCancel={() => {}} {...props} />);
        await screen.findByText('Record Supplier Payment');
    };

    it('opens with the create chrome and its three sections', async () => {
        await openCreate();

        ['Invoice', 'Payment', 'Notes'].forEach((section) => {
            expect(screen.getByRole('heading', { name: section })).toBeInTheDocument();
        });
        expect(screen.getByRole('button', { name: 'Record Payment' })).toHaveClass('btn-primary');
    });

    /** The completeness check against POST /purchase/payments. */
    it('carries every field the API accepts', async () => {
        const post = vi.spyOn(client, 'apiPost').mockResolvedValue({ data: { id: 1 } });
        await openCreate();

        fireEvent.change(screen.getByLabelText(/Invoice/), { target: { value: '4' } });
        fireEvent.change(screen.getByLabelText(/Payment Date/), { target: { value: '2026-09-10' } });
        fireEvent.change(screen.getByLabelText(/Amount/), { target: { value: '100' } });
        fireEvent.change(screen.getByLabelText(/Payment Method/), { target: { value: 'cheque' } });
        fireEvent.change(screen.getByLabelText(/Reference Number/), { target: { value: 'CHQ-77' } });
        fireEvent.change(screen.getByLabelText('Notes'), { target: { value: 'Part settlement' } });

        fireEvent.click(screen.getByRole('button', { name: 'Record Payment' }));

        await waitFor(() => expect(post).toHaveBeenCalledWith('/purchase/payments', {
            supplier_invoice_id: '4',
            payment_date: '2026-09-10',
            amount: '100',
            payment_method: 'cheque',
            reference_number: 'CHQ-77',
            notes: 'Part settlement',
        }));
    });

    it('offers all four payment methods with readable labels', async () => {
        await openCreate();

        const method = screen.getByLabelText(/Payment Method/);
        expect([...method.options].map((o) => o.value))
            .toEqual(['', 'cash', 'bank_transfer', 'cheque', 'other']);
        expect([...method.options].map((o) => o.text)).toContain('Bank Transfer');
    });

    /** So the amount can be judged against what is actually owed. */
    it('shows the outstanding balance once an invoice is chosen', async () => {
        await openCreate();

        expect(screen.queryByText(/Outstanding on this invoice/)).not.toBeInTheDocument();

        fireEvent.change(screen.getByLabelText(/Invoice/), { target: { value: '6' } });

        expect(screen.getByText(/Outstanding on this invoice/)).toBeInTheDocument();
        expect(screen.getByText('BD 80.000')).toBeInTheDocument();
    });

    it('honours the invoice the invoices page sent it', async () => {
        await openCreate({ presetInvoiceId: '6' });

        expect(screen.getByLabelText(/Invoice/)).toHaveValue('6');
    });

    /**
     * Moving a payment between invoices would need both balances resynced,
     * which the API's update endpoint does not accept — so on edit the
     * invoice is shown, not offered.
     */
    it('fixes the invoice when editing and omits it from the payload', async () => {
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({ data: { id: 9 } });
        const payment = {
            id: 9, supplier_invoice_id: 4, invoice_number: 'INV-4', supplier_name: 'Gulf Metals',
            payment_date: '2026-09-02', amount: '50', payment_method: 'cash',
            reference_number: 'R-1', notes: 'first',
        };

        render(<SupplierPaymentModal payment={payment} onSaved={() => {}} onCancel={() => {}} />);
        await screen.findByText('Edit Payment');

        expect(screen.queryByRole('combobox', { name: /Invoice/ })).not.toBeInTheDocument();
        expect(screen.getByText(/INV-4 — Gulf Metals/)).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText(/Amount/), { target: { value: '75' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save Changes' }));

        await waitFor(() => expect(put).toHaveBeenCalledWith('/purchase/payments/9', {
            payment_date: '2026-09-02',
            amount: '75',
            payment_method: 'cash',
            reference_number: 'R-1',
            notes: 'first',
        }));
        expect(put.mock.calls[0][1]).not.toHaveProperty('supplier_invoice_id');
    });

    /** Overpaying is refused with a plain message, not a field error. */
    it('surfaces an overpayment refusal in the summary', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({
            message: 'That would overpay the invoice. Outstanding is 80.00.',
        });
        await openCreate();

        fireEvent.change(screen.getByLabelText(/Invoice/), { target: { value: '6' } });
        fireEvent.change(screen.getByLabelText(/Amount/), { target: { value: '500' } });
        fireEvent.change(screen.getByLabelText(/Payment Method/), { target: { value: 'cash' } });
        fireEvent.click(screen.getByRole('button', { name: 'Record Payment' }));

        expect(await screen.findByRole('alert')).toHaveTextContent('That would overpay the invoice');
    });

    it('shows a field error against its field and in the summary', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({
            errors: { amount: ['The amount must be at least 0.01.'] },
        });
        await openCreate();

        fireEvent.change(screen.getByLabelText(/Invoice/), { target: { value: '6' } });
        fireEvent.change(screen.getByLabelText(/Amount/), { target: { value: '1' } });
        fireEvent.change(screen.getByLabelText(/Payment Method/), { target: { value: 'cash' } });
        fireEvent.click(screen.getByRole('button', { name: 'Record Payment' }));

        expect(await screen.findByRole('alert')).toHaveTextContent('at least 0.01');
        expect(screen.getAllByText('The amount must be at least 0.01.')).toHaveLength(2);
    });

    it('says so when the unpaid invoices cannot be loaded', async () => {
        vi.spyOn(client, 'apiGet').mockRejectedValue({ message: 'nope' });
        render(<SupplierPaymentModal payment={null} onSaved={() => {}} onCancel={() => {}} />);

        expect(await screen.findByRole('alert'))
            .toHaveTextContent('The list of unpaid invoices could not be loaded.');
    });
});
