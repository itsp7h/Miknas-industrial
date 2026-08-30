import { useEffect, useMemo, useState } from 'react';
import FormField from '../ui/FormField';
import Button from '../ui/Button';
import { apiGet, apiPost } from '../../api/client';

/**
 * Material issues and production output are the same shape — an order, an item,
 * a warehouse, a quantity and a date — differing only in which endpoint they
 * post to and how the item is chosen. One form serves both so they stay
 * consistent rather than drifting into two dialects.
 */
export default function FlowForm({ kind, onSaved, onCancel }) {
    const isIssue = kind === 'material-issue';
    const endpoint = isIssue ? '/production/material-issues' : '/production/outputs';
    const dateField = isIssue ? 'issue_date' : 'output_date';

    const [options, setOptions] = useState({ production_orders: [], warehouses: [], stock: [] });
    const [values, setValues] = useState({
        production_order_id: '',
        item_id: '',
        warehouse_id: '',
        quantity: '',
        [dateField]: new Date().toISOString().slice(0, 10),
        notes: '',
    });
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        apiGet(`${endpoint}/form-options`).then(setOptions).catch(() => {});
    }, [endpoint]);

    // Output always produces the order's own product, so the item follows the
    // chosen order rather than being picked separately.
    const selectedOrder = useMemo(
        () => options.production_orders.find((o) => String(o.id) === String(values.production_order_id)),
        [options.production_orders, values.production_order_id]
    );

    useEffect(() => {
        if (!isIssue && selectedOrder?.product_id) {
            setValues((prev) => ({ ...prev, item_id: selectedOrder.product_id }));
        }
    }, [isIssue, selectedOrder]);

    // For issues, only offer material that is actually in the chosen warehouse.
    const issuableStock = useMemo(
        () => (options.stock ?? []).filter((row) => String(row.warehouse_id) === String(values.warehouse_id) && Number(row.quantity) > 0),
        [options.stock, values.warehouse_id]
    );

    const onHand = useMemo(
        () => issuableStock.find((row) => String(row.item_id) === String(values.item_id))?.quantity,
        [issuableStock, values.item_id]
    );

    function setField(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
    }

    async function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const response = await apiPost(endpoint, values);
            onSaved(response.data);
        } catch (err) {
            setErrors(Object.fromEntries(
                Object.entries(err.errors ?? {}).map(([key, messages]) => [key, messages[0]])
            ));
            if (err.message && !err.errors) setErrors({ production_order_id: err.message });
        } finally {
            setSaving(false);
        }
    }

    return (
        <form onSubmit={handleSubmit}>
            <div className="mb-4">
                <label htmlFor="production_order_id" className="block text-sm font-medium text-gray-700 mb-1">Production Order</label>
                <select id="production_order_id" value={values.production_order_id}
                    onChange={(e) => setField('production_order_id', e.target.value)}
                    className={`border rounded-md px-3 py-2 text-sm w-full ${errors.production_order_id ? 'border-red-400' : 'border-gray-300'}`}>
                    <option value="">Select an open order…</option>
                    {options.production_orders.map((o) => (
                        <option key={o.id} value={o.id}>
                            {o.order_number}{o.product_name ? ` — ${o.product_name}` : ''}
                        </option>
                    ))}
                </select>
                {errors.production_order_id && <p className="text-sm text-red-600 mt-1">{errors.production_order_id}</p>}
            </div>

            <div className="mb-4">
                <label htmlFor="warehouse_id" className="block text-sm font-medium text-gray-700 mb-1">
                    {isIssue ? 'Issue From' : 'Receive Into'}
                </label>
                <select id="warehouse_id" value={values.warehouse_id}
                    onChange={(e) => setField('warehouse_id', e.target.value)}
                    className={`border rounded-md px-3 py-2 text-sm w-full ${errors.warehouse_id ? 'border-red-400' : 'border-gray-300'}`}>
                    <option value="">Select a warehouse…</option>
                    {options.warehouses.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
                </select>
                {errors.warehouse_id && <p className="text-sm text-red-600 mt-1">{errors.warehouse_id}</p>}
            </div>

            {isIssue ? (
                <div className="mb-4">
                    <label htmlFor="item_id" className="block text-sm font-medium text-gray-700 mb-1">Material</label>
                    <select id="item_id" value={values.item_id}
                        onChange={(e) => setField('item_id', e.target.value)}
                        className={`border rounded-md px-3 py-2 text-sm w-full ${errors.item_id ? 'border-red-400' : 'border-gray-300'}`}>
                        <option value="">{values.warehouse_id ? 'Select material…' : 'Choose a warehouse first'}</option>
                        {issuableStock.map((row) => (
                            <option key={row.item_id} value={row.item_id}>{row.item_name} ({row.quantity} on hand)</option>
                        ))}
                    </select>
                    {errors.item_id && <p className="text-sm text-red-600 mt-1">{errors.item_id}</p>}
                    {onHand !== undefined && (
                        <p style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>{onHand} on hand in this warehouse.</p>
                    )}
                </div>
            ) : (
                <p style={{ fontSize: 13, color: '#64748b', marginBottom: 16 }}>
                    {selectedOrder
                        ? <>Producing <strong>{selectedOrder.product_name}</strong> — {selectedOrder.quantity_produced} of {selectedOrder.quantity_to_produce} made so far.</>
                        : 'Select an order to see what it produces.'}
                </p>
            )}

            <FormField label="Quantity" name="quantity" type="number" value={values.quantity} onChange={setField} error={errors.quantity} />
            <FormField label={isIssue ? 'Issue Date' : 'Output Date'} name={dateField} type="date" value={values[dateField]} onChange={setField} error={errors[dateField]} />
            <FormField label="Notes" name="notes" type="textarea" value={values.notes} onChange={setField} error={errors.notes} />

            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                <Button variant="secondary" onClick={onCancel}>Cancel</Button>
                <Button type="submit" loading={saving}>{isIssue ? 'Issue Material' : 'Record Output'}</Button>
            </div>
        </form>
    );
}
