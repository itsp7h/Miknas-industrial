import { useMemo, useState } from 'react';
import { apiPut } from '../../../api/client';
import { money } from '../order/statuses';

/**
 * Blade's edit page, minus its Status field: status follows the receipts against
 * the invoice, and typing it made an invoice read "paid" with no money behind
 * it. The amounts are editable only until the first receipt lands, because the
 * customer's outstanding balance was raised by the original total.
 */
export default function InvoiceEditForm({ invoice, onSaved, onCancel }) {
    const locked = Number(invoice.paid_amount ?? 0) > 0;
    const [values, setValues] = useState({
        invoice_date: invoice.invoice_date ?? '',
        due_date: invoice.due_date ?? '',
        subtotal: invoice.subtotal ?? '',
        vat_rate: invoice.vat_rate ?? 0,
        notes: invoice.notes ?? '',
    });
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    // Mirrors the server's arithmetic so the total is visible before saving.
    const total = useMemo(() => {
        const subtotal = Number(values.subtotal) || 0;
        const vat = Math.round(((subtotal * (Number(values.vat_rate) || 0)) / 100) * 100) / 100;
        return { vat, total: Math.round((subtotal + vat) * 100) / 100 };
    }, [values.subtotal, values.vat_rate]);

    function setField(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
    }

    async function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        const payload = {
            invoice_date: values.invoice_date,
            due_date: values.due_date || null,
            notes: values.notes || null,
        };
        if (!locked) {
            payload.subtotal = values.subtotal;
            payload.vat_rate = values.vat_rate;
        }
        try {
            const response = await apiPut(`/sales/invoices/${invoice.id}`, payload);
            onSaved(response.data);
        } catch (err) {
            setErrors(Object.fromEntries(
                Object.entries(err.errors ?? {}).map(([key, messages]) => [key, messages[0]])
            ));
            if (err.message && !err.errors) setErrors({ invoice_date: err.message });
        } finally {
            setSaving(false);
        }
    }

    const messages = Object.values(errors);

    return (
        <form onSubmit={handleSubmit}>
            {messages.length > 0 && (
                <div className="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                    <ul className="list-disc list-inside space-y-1">
                        {messages.map((message) => <li key={message}>{message}</li>)}
                    </ul>
                </div>
            )}

            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label htmlFor="invoice_date" className="form-label">
                        Invoice Date <span className="text-red-500">*</span>
                    </label>
                    <input
                        id="invoice_date" className="form-input" type="date" required
                        value={values.invoice_date} onChange={(e) => setField('invoice_date', e.target.value)}
                    />
                </div>

                <div>
                    <label htmlFor="due_date" className="form-label">Due Date</label>
                    <input
                        id="due_date" className="form-input" type="date"
                        value={values.due_date} onChange={(e) => setField('due_date', e.target.value)}
                    />
                </div>

                <div>
                    <label htmlFor="subtotal" className="form-label">Subtotal</label>
                    <input
                        id="subtotal" className="form-input" type="number" min="0" step="0.01" disabled={locked}
                        value={values.subtotal} onChange={(e) => setField('subtotal', e.target.value)}
                    />
                </div>

                <div>
                    <label htmlFor="vat_rate" className="form-label">VAT Rate (%)</label>
                    <input
                        id="vat_rate" className="form-input" type="number" min="0" max="100" step="0.01" disabled={locked}
                        value={values.vat_rate} onChange={(e) => setField('vat_rate', e.target.value)}
                    />
                </div>

                <div className="sm:col-span-2">
                    <label className="form-label">Total Amount</label>
                    <div
                        className="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm font-semibold text-gray-800"
                        aria-label="Total Amount"
                    >
                        {money(total.total)}
                        <span className="text-gray-500 font-normal"> (VAT {money(total.vat)})</span>
                    </div>
                </div>

                <div className="sm:col-span-2">
                    <label htmlFor="notes" className="form-label">Notes</label>
                    <textarea
                        id="notes" className="form-textarea" rows={2}
                        value={values.notes} onChange={(e) => setField('notes', e.target.value)}
                    />
                </div>
            </div>

            {locked && (
                <p style={{ fontSize: 12, color: '#64748b', marginTop: 10 }}>
                    {money(invoice.paid_amount)} has been received against this invoice, so its amounts are fixed.
                    Status follows the receipts and is not set by hand.
                </p>
            )}

            <div className="mt-6 flex items-center gap-3">
                <button type="submit" className="btn-primary" disabled={saving}>
                    {saving ? 'Saving…' : 'Update Invoice'}
                </button>
                <button type="button" onClick={onCancel} className="btn-secondary">Cancel</button>
            </div>
        </form>
    );
}
