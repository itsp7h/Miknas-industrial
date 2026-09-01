import { useEffect, useMemo, useState } from 'react';
import FormField from '../../ui/FormField';
import Button from '../../ui/Button';
import { apiGet, apiPost } from '../../../api/client';

export default function DeliveryNoteForm({ presetOrderId, onSaved, onCancel }) {
    const [options, setOptions] = useState({ orders: [], warehouses: [] });
    const [values, setValues] = useState({
        sales_order_id: presetOrderId ?? '',
        warehouse_id: '',
        delivery_date: new Date().toISOString().slice(0, 10),
        notes: '',
    });
    const [quantities, setQuantities] = useState({});
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        apiGet('/sales/delivery-notes/form-options').then(setOptions).catch(() => {});
    }, []);

    const order = useMemo(
        () => options.orders.find((o) => String(o.id) === String(values.sales_order_id)),
        [options.orders, values.sales_order_id]
    );

    // Only lines with something left to deliver are worth showing.
    const deliverable = useMemo(
        () => (order?.items ?? []).filter((line) => Number(line.outstanding) > 0),
        [order]
    );

    function setField(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
        if (name === 'sales_order_id') setQuantities({});
    }

    async function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        const items = deliverable
            .map((line) => ({ item_id: line.item_id, quantity: quantities[line.item_id] }))
            .filter((line) => Number(line.quantity) > 0);
        try {
            const response = await apiPost('/sales/delivery-notes', { ...values, items });
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
                <label htmlFor="sales_order_id" className="block text-sm font-medium text-gray-700 mb-1">Sales Order</label>
                <select
                    id="sales_order_id"
                    value={values.sales_order_id}
                    onChange={(e) => setField('sales_order_id', e.target.value)}
                    className={`border rounded-md px-3 py-2 text-sm w-full ${errors.sales_order_id ? 'border-red-400' : 'border-gray-300'}`}
                >
                    <option value="">Select a confirmed order…</option>
                    {options.orders.map((o) => (
                        <option key={o.id} value={o.id}>{o.order_number} — {o.customer_name}</option>
                    ))}
                </select>
                {errors.sales_order_id && <p className="text-sm text-red-600 mt-1">{errors.sales_order_id}</p>}
            </div>

            <div className="mb-4">
                <label htmlFor="warehouse_id" className="block text-sm font-medium text-gray-700 mb-1">Dispatch From</label>
                <select
                    id="warehouse_id"
                    value={values.warehouse_id}
                    onChange={(e) => setField('warehouse_id', e.target.value)}
                    className={`border rounded-md px-3 py-2 text-sm w-full ${errors.warehouse_id ? 'border-red-400' : 'border-gray-300'}`}
                >
                    <option value="">Select a warehouse…</option>
                    {options.warehouses.map((w) => (
                        <option key={w.id} value={w.id}>{w.name}</option>
                    ))}
                </select>
                {errors.warehouse_id && <p className="text-sm text-red-600 mt-1">{errors.warehouse_id}</p>}
            </div>

            <FormField label="Delivery Date" name="delivery_date" type="date" value={values.delivery_date} onChange={setField} error={errors.delivery_date} />

            {order && (
                <div className="mb-4">
                    <span className="text-sm font-medium text-gray-700">Quantities to deliver</span>
                    {deliverable.length === 0 && (
                        <p style={{ fontSize: 13, color: '#64748b', marginTop: 6 }}>
                            Every line on this order has already been delivered.
                        </p>
                    )}
                    {deliverable.map((line, index) => (
                        <div key={line.item_id} style={{ border: '1px solid #e2e8f0', borderRadius: 8, padding: 10, marginTop: 8 }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8, alignItems: 'center' }}>
                                <div>
                                    <div style={{ fontWeight: 600 }}>{line.item_name}</div>
                                    <div style={{ fontSize: 12, color: '#64748b' }}>
                                        {line.outstanding} of {line.quantity} outstanding
                                    </div>
                                </div>
                                <input
                                    type="number" step="0.01" min="0" max={line.outstanding}
                                    aria-label={`Quantity for ${line.item_name}`}
                                    value={quantities[line.item_id] ?? ''}
                                    onChange={(e) => setQuantities((prev) => ({ ...prev, [line.item_id]: e.target.value }))}
                                    className="border border-gray-300 rounded-md px-3 py-2 text-sm"
                                    style={{ width: 110 }}
                                />
                            </div>
                            {errors[`items.${index}.quantity`] && (
                                <p className="text-sm text-red-600 mt-1">{errors[`items.${index}.quantity`]}</p>
                            )}
                        </div>
                    ))}
                    {errors.items && <p className="text-sm text-red-600 mt-1">{errors.items}</p>}
                </div>
            )}

            <FormField label="Notes" name="notes" type="textarea" value={values.notes} onChange={setField} error={errors.notes} />

            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                <Button variant="secondary" onClick={onCancel}>Cancel</Button>
                <Button type="submit" loading={saving}>Create Delivery Note</Button>
            </div>
        </form>
    );
}
