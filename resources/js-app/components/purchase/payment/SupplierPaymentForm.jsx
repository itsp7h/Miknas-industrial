import { useEffect, useMemo, useState } from 'react';
import Button from '../../ui/Button';
import FormField from '../../ui/FormField';
import { apiGet, apiPost, apiPut } from '../../../api/client';
import { METHOD_LABELS, money } from './paymentStyles';

/**
 * The invoice can only be chosen when recording a new payment — moving an
 * existing payment between invoices would need both balances resynced, which the
 * Blade edit form never attempted either.
 */
export default function SupplierPaymentForm({ payment, presetInvoiceId, onSaved, onCancel }) {
    const isEdit = !!payment;
    const [options, setOptions] = useState({ invoices: [], methods: [] });
    const [values, setValues] = useState(() => ({
        supplier_invoice_id: payment?.supplier_invoice_id ?? (presetInvoiceId ? String(presetInvoiceId) : ''),
        payment_date: payment?.payment_date ?? new Date().toISOString().slice(0, 10),
        amount: payment?.amount ?? '',
        payment_method: payment?.payment_method ?? '',
        reference_number: payment?.reference_number ?? '',
        notes: payment?.notes ?? '',
    }));
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        apiGet('/purchase/payments/form-options').then(setOptions).catch(() => {});
    }, []);

    const selected = useMemo(
        () => options.invoices.find((i) => String(i.id) === String(values.supplier_invoice_id)) ?? null,
        [options.invoices, values.supplier_invoice_id]
    );

    function setField(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
    }

    async function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        setErrors({});

        const payload = {
            payment_date: values.payment_date,
            amount: values.amount,
            payment_method: values.payment_method,
            reference_number: values.reference_number || null,
            notes: values.notes || null,
            ...(isEdit ? {} : { supplier_invoice_id: values.supplier_invoice_id }),
        };

        try {
            const response = isEdit
                ? await apiPut(`/purchase/payments/${payment.id}`, payload)
                : await apiPost('/purchase/payments', payload);
            onSaved(response.data);
        } catch (err) {
            setErrors(Object.fromEntries(
                Object.entries(err.errors ?? {}).map(([key, messages]) => [key, messages[0]])
            ));
        } finally {
            setSaving(false);
        }
    }

    const selectClass = (error) =>
        `border rounded-md px-3 py-2 text-sm w-full ${error ? 'border-red-400' : 'border-gray-300'}`;

    return (
        <form onSubmit={handleSubmit}>
            {isEdit ? (
                <div className="mb-4">
                    <span className="block text-sm font-medium text-gray-700 mb-1">Invoice</span>
                    <div style={{ fontSize: 14, fontWeight: 600, color: '#0f172a' }}>
                        {payment.invoice_number}
                        {payment.supplier_name ? ` — ${payment.supplier_name}` : ''}
                    </div>
                </div>
            ) : (
                <div className="mb-4">
                    <label htmlFor="supplier_invoice_id" className="block text-sm font-medium text-gray-700 mb-1">
                        Invoice
                    </label>
                    <select
                        id="supplier_invoice_id"
                        value={values.supplier_invoice_id}
                        onChange={(e) => setField('supplier_invoice_id', e.target.value)}
                        className={selectClass(errors.supplier_invoice_id)}
                    >
                        <option value="">Select an unpaid invoice…</option>
                        {options.invoices.map((invoice) => (
                            <option key={invoice.id} value={invoice.id}>
                                {invoice.invoice_number}
                                {invoice.supplier_name ? ` - ${invoice.supplier_name}` : ''}
                                {` (Outstanding: ${money(invoice.outstanding)})`}
                            </option>
                        ))}
                    </select>
                    {errors.supplier_invoice_id && <p className="text-sm text-red-600 mt-1">{errors.supplier_invoice_id}</p>}
                </div>
            )}

            <FormField label="Payment Date" name="payment_date" type="date" value={values.payment_date} onChange={setField} error={errors.payment_date} />
            <FormField label="Amount" name="amount" type="number" value={values.amount} onChange={setField} error={errors.amount} />

            {/* Shown so the amount can be judged against what is actually owed. */}
            {selected && !isEdit && (
                <p style={{ fontSize: 12, color: '#64748b', margin: '-8px 0 12px' }}>
                    Outstanding on this invoice: <strong>{money(selected.outstanding)}</strong>
                </p>
            )}

            <div className="mb-4">
                <label htmlFor="payment_method" className="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                <select
                    id="payment_method"
                    value={values.payment_method}
                    onChange={(e) => setField('payment_method', e.target.value)}
                    className={selectClass(errors.payment_method)}
                >
                    <option value="">Select a method…</option>
                    {(options.methods.length ? options.methods : Object.keys(METHOD_LABELS)).map((m) => (
                        <option key={m} value={m}>{METHOD_LABELS[m] ?? m}</option>
                    ))}
                </select>
                {errors.payment_method && <p className="text-sm text-red-600 mt-1">{errors.payment_method}</p>}
            </div>

            <FormField label="Reference Number" name="reference_number" value={values.reference_number} onChange={setField} error={errors.reference_number} />
            <FormField label="Notes" name="notes" type="textarea" value={values.notes} onChange={setField} error={errors.notes} />

            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                <Button variant="secondary" onClick={onCancel}>Cancel</Button>
                <Button type="submit" loading={saving}>{isEdit ? 'Update Payment' : 'Record Payment'}</Button>
            </div>
        </form>
    );
}
