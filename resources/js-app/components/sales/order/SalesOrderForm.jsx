import { useEffect, useMemo, useState } from 'react';
import FormField from '../../ui/FormField';
import Button from '../../ui/Button';
import { apiGet, apiPost, apiPut } from '../../../api/client';

const money = (value) => Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const emptyLine = () => ({ item_id: '', quantity: '', price: '' });

export default function SalesOrderForm({ order, onSaved, onCancel }) {
    const [options, setOptions] = useState({ customers: [], items: [] });
    const [values, setValues] = useState(() => ({
        customer_id: order?.customer_id ?? '',
        order_date: order?.order_date ?? new Date().toISOString().slice(0, 10),
        delivery_date: order?.delivery_date ?? '',
        notes: order?.notes ?? '',
    }));
    const [lines, setLines] = useState(() =>
        order?.items?.length
            ? order.items.map((line) => ({ item_id: line.item_id, quantity: line.quantity, price: line.price }))
            : [emptyLine()]
    );
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        apiGet('/sales/orders/form-options').then(setOptions).catch(() => {});
    }, []);

    const total = useMemo(
        () => lines.reduce((sum, line) => sum + (Number(line.quantity) || 0) * (Number(line.price) || 0), 0),
        [lines]
    );

    function setField(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
    }

    function setLine(index, name, value) {
        setLines((prev) => prev.map((line, i) => (i === index ? { ...line, [name]: value } : line)));
    }

    function addLine() {
        setLines((prev) => [...prev, emptyLine()]);
    }

    function removeLine(index) {
        // Always leave one row so the form is never itemless.
        setLines((prev) => (prev.length === 1 ? prev : prev.filter((_, i) => i !== index)));
    }

    async function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        const payload = { ...values, delivery_date: values.delivery_date || null, items: lines };
        try {
            const response = order
                ? await apiPut(`/sales/orders/${order.id}`, payload)
                : await apiPost('/sales/orders', payload);
            onSaved(response.data);
        } catch (err) {
            setErrors(Object.fromEntries(
                Object.entries(err.errors ?? {}).map(([key, messages]) => [key, messages[0]])
            ));
        } finally {
            setSaving(false);
        }
    }

    // Validation errors on lines come back keyed as items.0.quantity.
    const lineError = (index, field) => errors[`items.${index}.${field}`];

    return (
        <form onSubmit={handleSubmit}>
            <div className="mb-4">
                <label htmlFor="customer_id" className="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                <select
                    id="customer_id"
                    value={values.customer_id}
                    onChange={(e) => setField('customer_id', e.target.value)}
                    className={`border rounded-md px-3 py-2 text-sm w-full ${errors.customer_id ? 'border-red-400' : 'border-gray-300'}`}
                >
                    <option value="">Select a customer…</option>
                    {options.customers.map((customer) => (
                        <option key={customer.id} value={customer.id}>{customer.name}</option>
                    ))}
                </select>
                {errors.customer_id && <p className="text-sm text-red-600 mt-1">{errors.customer_id}</p>}
            </div>

            <FormField label="Order Date" name="order_date" type="date" value={values.order_date} onChange={setField} error={errors.order_date} />
            <FormField label="Delivery Date" name="delivery_date" type="date" value={values.delivery_date} onChange={setField} error={errors.delivery_date} />

            <div className="mb-2 flex items-center justify-between">
                <span className="text-sm font-medium text-gray-700">Line Items</span>
                <Button variant="secondary" onClick={addLine}>Add line</Button>
            </div>
            {errors.items && <p className="text-sm text-red-600 mb-2">{errors.items}</p>}

            {lines.map((line, index) => (
                <div key={index} style={{ border: '1px solid #e2e8f0', borderRadius: 8, padding: 10, marginBottom: 8 }}>
                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'flex-end' }}>
                        <div style={{ flex: '2 1 180px' }}>
                            <label htmlFor={`item_${index}`} className="block text-xs font-medium text-gray-700 mb-1">Item</label>
                            <select
                                id={`item_${index}`}
                                value={line.item_id}
                                onChange={(e) => setLine(index, 'item_id', e.target.value)}
                                className={`border rounded-md px-3 py-2 text-sm w-full ${lineError(index, 'item_id') ? 'border-red-400' : 'border-gray-300'}`}
                            >
                                <option value="">Select…</option>
                                {options.items.map((item) => (
                                    <option key={item.id} value={item.id}>{item.item_name}</option>
                                ))}
                            </select>
                        </div>
                        <div style={{ flex: '1 1 90px' }}>
                            <label htmlFor={`qty_${index}`} className="block text-xs font-medium text-gray-700 mb-1">Quantity</label>
                            <input
                                id={`qty_${index}`} type="number" step="0.01" value={line.quantity}
                                onChange={(e) => setLine(index, 'quantity', e.target.value)}
                                className={`border rounded-md px-3 py-2 text-sm w-full ${lineError(index, 'quantity') ? 'border-red-400' : 'border-gray-300'}`}
                            />
                        </div>
                        <div style={{ flex: '1 1 90px' }}>
                            <label htmlFor={`price_${index}`} className="block text-xs font-medium text-gray-700 mb-1">Price</label>
                            <input
                                id={`price_${index}`} type="number" step="0.01" value={line.price}
                                onChange={(e) => setLine(index, 'price', e.target.value)}
                                className={`border rounded-md px-3 py-2 text-sm w-full ${lineError(index, 'price') ? 'border-red-400' : 'border-gray-300'}`}
                            />
                        </div>
                        <div style={{ flex: '1 1 90px', textAlign: 'right', paddingBottom: 8 }}>
                            <div className="text-xs text-gray-500">Line total</div>
                            <div style={{ fontWeight: 600 }}>{money((Number(line.quantity) || 0) * (Number(line.price) || 0))}</div>
                        </div>
                        {lines.length > 1 && (
                            <Button variant="link-danger" onClick={() => removeLine(index)}>Remove</Button>
                        )}
                    </div>
                    {['item_id', 'quantity', 'price'].map((field) =>
                        lineError(index, field) ? (
                            <p key={field} className="text-sm text-red-600 mt-1">{lineError(index, field)}</p>
                        ) : null
                    )}
                </div>
            ))}

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 12, alignItems: 'baseline', margin: '12px 0' }}>
                <span className="text-sm text-gray-500">Order total</span>
                <span style={{ fontSize: 18, fontWeight: 700 }}>{money(total)}</span>
            </div>

            <FormField label="Notes" name="notes" type="textarea" value={values.notes} onChange={setField} error={errors.notes} />

            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                <Button variant="secondary" onClick={onCancel}>Cancel</Button>
                <Button type="submit" loading={saving}>{order ? 'Save Changes' : 'Create Order'}</Button>
            </div>
        </form>
    );
}
