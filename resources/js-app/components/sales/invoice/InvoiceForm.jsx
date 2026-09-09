import { useEffect, useMemo, useState } from 'react';
import FormField from '../../ui/FormField';
import Button from '../../ui/Button';
import { apiGet, apiPost } from '../../../api/client';
import { money } from '../order/statuses';

export default function InvoiceForm({ onSaved, onCancel }) {
    const [options, setOptions] = useState({ orders: [], vat_rate: 0 });
    const [values, setValues] = useState({
        sales_order_id: '',
        invoice_date: new Date().toISOString().slice(0, 10),
        due_date: '',
        notes: '',
    });
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        apiGet('/sales/invoices/form-options').then(setOptions).catch(() => {});
    }, []);

    const order = useMemo(
        () => options.orders.find((o) => String(o.id) === String(values.sales_order_id)),
        [options.orders, values.sales_order_id]
    );

    // Mirrors the server's calculation so the figures are visible before saving;
    // the server derives them again and its numbers are the ones stored.
    const preview = useMemo(() => {
        const subtotal = Number(order?.total_amount ?? 0);
        const vat = Math.round(((subtotal * Number(options.vat_rate)) / 100) * 100) / 100;
        return { subtotal, vat, total: Math.round((subtotal + vat) * 100) / 100 };
    }, [order, options.vat_rate]);

    function setField(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
    }

    async function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const response = await apiPost('/sales/invoices', { ...values, due_date: values.due_date || null });
            onSaved(response.data);
        } catch (err) {
            setErrors(Object.fromEntries(
                Object.entries(err.errors ?? {}).map(([key, messages]) => [key, messages[0]])
            ));
            if (err.message && !err.errors) setErrors({ sales_order_id: err.message });
        } finally {
            setSaving(false);
        }
    }

    return (
        <form onSubmit={handleSubmit}>
            <div className="mb-4">
                <label htmlFor="sales_order_id" className="block text-sm font-medium text-gray-700 mb-1">Sales Order</label>
                <select
                    id="sales_order_id"
                    value={values.sales_order_id}
                    onChange={(e) => setField('sales_order_id', e.target.value)}
                    className={`border rounded-md px-3 py-2 text-sm w-full ${errors.sales_order_id ? 'border-red-400' : 'border-gray-300'}`}
                >
                    <option value="">Select an order to invoice…</option>
                    {options.orders.map((o) => (
                        <option key={o.id} value={o.id}>{o.order_number} — {o.customer_name}</option>
                    ))}
                </select>
                {errors.sales_order_id && <p className="text-sm text-red-600 mt-1">{errors.sales_order_id}</p>}
                {options.orders.length === 0 && (
                    <p style={{ fontSize: 13, color: '#64748b', marginTop: 6 }}>
                        No confirmed orders are waiting to be invoiced.
                    </p>
                )}
            </div>

            <FormField label="Invoice Date" name="invoice_date" type="date" value={values.invoice_date} onChange={setField} error={errors.invoice_date} />
            <FormField label="Due Date" name="due_date" type="date" value={values.due_date} onChange={setField} error={errors.due_date} />

            {order && (
                <div style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 8, padding: 12, marginBottom: 16 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}>
                        <span>Subtotal</span><span>{money(preview.subtotal)}</span>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}>
                        <span>VAT ({options.vat_rate}%)</span><span>{money(preview.vat)}</span>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontWeight: 700, marginTop: 6 }}>
                        <span>Total</span><span>{money(preview.total)}</span>
                    </div>
                    <p style={{ fontSize: 11, color: '#94a3b8', marginTop: 6 }}>
                        Amounts come from the order and the configured VAT rate.
                    </p>
                </div>
            )}

            <FormField label="Notes" name="notes" type="textarea" value={values.notes} onChange={setField} error={errors.notes} />

            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                <Button variant="secondary" onClick={onCancel}>Cancel</Button>
                <Button type="submit" loading={saving}>Create Invoice</Button>
            </div>
        </form>
    );
}
