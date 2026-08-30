import { useEffect, useMemo, useState } from 'react';
import FormField from '../../ui/FormField';
import Button from '../../ui/Button';
import { apiGet, apiPost } from '../../../api/client';
import { money } from '../order/statuses';

export const METHOD_LABELS = {
    cash: 'Cash',
    bank_transfer: 'Bank Transfer',
    cheque: 'Cheque',
    other: 'Other',
};

export default function PaymentForm({ onSaved, onCancel }) {
    const [options, setOptions] = useState({ invoices: [], payment_methods: [] });
    const [values, setValues] = useState({
        sales_invoice_id: '',
        receipt_date: new Date().toISOString().slice(0, 10),
        amount: '',
        payment_method: 'cash',
        reference_number: '',
        notes: '',
    });
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        apiGet('/sales/payments/form-options').then(setOptions).catch(() => {});
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

    return (
        <form onSubmit={handleSubmit}>
            <div className="mb-4">
                <label htmlFor="sales_invoice_id" className="block text-sm font-medium text-gray-700 mb-1">Invoice</label>
                <select
                    id="sales_invoice_id"
                    value={values.sales_invoice_id}
                    onChange={(e) => setField('sales_invoice_id', e.target.value)}
                    className={`border rounded-md px-3 py-2 text-sm w-full ${errors.sales_invoice_id ? 'border-red-400' : 'border-gray-300'}`}
                >
                    <option value="">Select an unsettled invoice…</option>
                    {options.invoices.map((i) => (
                        <option key={i.id} value={i.id}>
                            {i.invoice_number} — {i.customer_name} ({money(i.balance_due)} due)
                        </option>
                    ))}
                </select>
                {errors.sales_invoice_id && <p className="text-sm text-red-600 mt-1">{errors.sales_invoice_id}</p>}
                {options.invoices.length === 0 && (
                    <p style={{ fontSize: 13, color: '#64748b', marginTop: 6 }}>Every invoice is settled.</p>
                )}
            </div>

            {invoice && (
                <p style={{ fontSize: 13, color: '#64748b', marginBottom: 12 }}>
                    Outstanding on this invoice: <strong>{money(invoice.balance_due)}</strong>
                </p>
            )}

            <FormField label="Receipt Date" name="receipt_date" type="date" value={values.receipt_date} onChange={setField} error={errors.receipt_date} />
            <FormField label="Amount" name="amount" type="number" value={values.amount} onChange={setField} error={errors.amount} />

            <div className="mb-4">
                <label htmlFor="payment_method" className="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                <select
                    id="payment_method"
                    value={values.payment_method}
                    onChange={(e) => setField('payment_method', e.target.value)}
                    className={`border rounded-md px-3 py-2 text-sm w-full ${errors.payment_method ? 'border-red-400' : 'border-gray-300'}`}
                >
                    {(options.payment_methods.length ? options.payment_methods : Object.keys(METHOD_LABELS)).map((method) => (
                        <option key={method} value={method}>{METHOD_LABELS[method] ?? method}</option>
                    ))}
                </select>
                {errors.payment_method && <p className="text-sm text-red-600 mt-1">{errors.payment_method}</p>}
            </div>

            <FormField label="Reference Number" name="reference_number" value={values.reference_number} onChange={setField} error={errors.reference_number} />
            <FormField label="Notes" name="notes" type="textarea" value={values.notes} onChange={setField} error={errors.notes} />

            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                <Button variant="secondary" onClick={onCancel}>Cancel</Button>
                <Button type="submit" loading={saving}>Record Payment</Button>
            </div>
        </form>
    );
}
