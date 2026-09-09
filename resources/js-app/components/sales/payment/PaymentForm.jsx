import { useEffect, useMemo, useState } from 'react';
import { apiGet, apiPost } from '../../../api/client';
import { money } from '../order/statuses';
import { METHOD_LABELS } from './methods';

const NO_OPTIONS = { invoices: [], payment_methods: [] };

/**
 * Blade's create page, two-up in a card: invoice across the top, then date,
 * amount, method, reference and notes. Each invoice option names its
 * outstanding figure, as Blade's did, and the amount is capped at it — the
 * server refuses an overpayment because it would drive the customer's balance
 * negative.
 */
export default function PaymentForm({ presetInvoiceId, onSaved, onCancel }) {
    const [options, setOptions] = useState(NO_OPTIONS);
    const [values, setValues] = useState({
        sales_invoice_id: presetInvoiceId ?? '',
        receipt_date: new Date().toISOString().slice(0, 10),
        amount: '',
        payment_method: 'cash',
        reference_number: '',
        notes: '',
    });
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        apiGet('/sales/payments/form-options')
            .then((response) => setOptions({ ...NO_OPTIONS, ...response }))
            .catch(() => {});
    }, []);

    const invoice = useMemo(
        () => options.invoices.find((i) => String(i.id) === String(values.sales_invoice_id)),
        [options.invoices, values.sales_invoice_id]
    );

    function setField(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
    }

    async function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const response = await apiPost('/sales/payments', values);
            onSaved(response.data);
        } catch (err) {
            setErrors(Object.fromEntries(
                Object.entries(err.errors ?? {}).map(([key, messages]) => [key, messages[0]])
            ));
        } finally {
            setSaving(false);
        }
    }

    const messages = Object.values(errors);
    const methods = options.payment_methods.length ? options.payment_methods : Object.keys(METHOD_LABELS);

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
                <div className="sm:col-span-2">
                    <label htmlFor="sales_invoice_id" className="form-label">
                        Invoice <span className="text-red-500">*</span>
                    </label>
                    <select
                        id="sales_invoice_id" className="form-select" required
                        value={values.sales_invoice_id}
                        onChange={(e) => setField('sales_invoice_id', e.target.value)}
                    >
                        <option value="">-- Select Unpaid Invoice --</option>
                        {options.invoices.map((i) => (
                            <option key={i.id} value={i.id}>
                                {i.invoice_number} - {i.customer_name} (Outstanding: {money(i.balance_due)})
                            </option>
                        ))}
                    </select>
                    {options.invoices.length === 0 && (
                        <p style={{ fontSize: 13, color: '#64748b', marginTop: 6 }}>Every invoice is settled.</p>
                    )}
                </div>

                <div>
                    <label htmlFor="receipt_date" className="form-label">
                        Receipt Date <span className="text-red-500">*</span>
                    </label>
                    <input
                        id="receipt_date" className="form-input" type="date" required
                        value={values.receipt_date}
                        onChange={(e) => setField('receipt_date', e.target.value)}
                    />
                </div>

                <div>
                    <label htmlFor="amount" className="form-label">
                        Amount <span className="text-red-500">*</span>
                    </label>
                    <input
                        id="amount" className="form-input" type="number" min="0.01" step="0.01" required
                        max={invoice ? invoice.balance_due : undefined}
                        value={values.amount}
                        onChange={(e) => setField('amount', e.target.value)}
                    />
                    {invoice && (
                        <p style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                            {money(invoice.balance_due)} outstanding on this invoice.
                        </p>
                    )}
                </div>

                <div>
                    <label htmlFor="payment_method" className="form-label">
                        Payment Method <span className="text-red-500">*</span>
                    </label>
                    <select
                        id="payment_method" className="form-select" required
                        value={values.payment_method}
                        onChange={(e) => setField('payment_method', e.target.value)}
                    >
                        {methods.map((method) => (
                            <option key={method} value={method}>{METHOD_LABELS[method] ?? method}</option>
                        ))}
                    </select>
                </div>

                <div>
                    <label htmlFor="reference_number" className="form-label">Reference Number</label>
                    <input
                        id="reference_number" className="form-input" type="text"
                        value={values.reference_number}
                        onChange={(e) => setField('reference_number', e.target.value)}
                    />
                </div>

                <div className="sm:col-span-2">
                    <label htmlFor="notes" className="form-label">Notes</label>
                    <textarea
                        id="notes" className="form-textarea" rows={2}
                        value={values.notes}
                        onChange={(e) => setField('notes', e.target.value)}
                    />
                </div>
            </div>

            <div className="mt-6 flex items-center gap-3">
                <button type="submit" className="btn-primary" disabled={saving}>
                    {saving ? 'Recording…' : 'Record Receipt'}
                </button>
                <button type="button" onClick={onCancel} className="btn-secondary">Cancel</button>
            </div>
        </form>
    );
}
