import { useEffect, useMemo, useState } from 'react';
import { apiGet, apiPost } from '../../../api/client';

const NO_OPTIONS = { orders: [], warehouses: [] };

/**
 * Blade's create page: a "Delivery Details" card two-up, then an "Items to
 * Deliver" table whose rows load from the chosen order (Product / SO Qty /
 * Deliver Qty). Restyled onto the shared form classes it used.
 *
 * One behavioural difference kept from the React version: Blade capped each row
 * at the ordered quantity, ignoring what had already been delivered, so a second
 * delivery could ship the full amount twice. Rows here show what is still
 * outstanding and cap on that, and a fully delivered line drops out.
 */
export default function DeliveryNoteForm({ presetOrderId, onSaved, onCancel }) {
    const [options, setOptions] = useState(NO_OPTIONS);
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
        apiGet('/sales/delivery-notes/form-options')
            .then((response) => setOptions({ ...NO_OPTIONS, ...response }))
            .catch(() => {});
    }, []);

    const order = useMemo(
        () => options.orders.find((o) => String(o.id) === String(values.sales_order_id)),
        [options.orders, values.sales_order_id]
    );

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

    const messages = Object.entries(errors)
        .filter(([key]) => !key.startsWith('items.'))
        .map(([, message]) => message);

    return (
        <form onSubmit={handleSubmit}>
            {messages.length > 0 && (
                <div className="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                    <ul className="list-disc list-inside space-y-1">
                        {messages.map((message) => <li key={message}>{message}</li>)}
                    </ul>
                </div>
            )}

            <div className="card card-body mb-4">
                <h2 className="text-base font-semibold text-gray-700 mb-4">Delivery Details</h2>
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label htmlFor="sales_order_id" className="form-label">
                            Sales Order <span className="text-red-500">*</span>
                        </label>
                        <select
                            id="sales_order_id" className="form-select" required
                            value={values.sales_order_id}
                            onChange={(e) => setField('sales_order_id', e.target.value)}
                        >
                            <option value="">-- Select Confirmed Order --</option>
                            {options.orders.map((o) => (
                                <option key={o.id} value={o.id}>
                                    {o.order_number}{o.customer_name ? ` - ${o.customer_name}` : ''}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label htmlFor="warehouse_id" className="form-label">
                            Warehouse <span className="text-red-500">*</span>
                        </label>
                        <select
                            id="warehouse_id" className="form-select" required
                            value={values.warehouse_id}
                            onChange={(e) => setField('warehouse_id', e.target.value)}
                        >
                            <option value="">-- Select Warehouse --</option>
                            {options.warehouses.map((w) => (
                                <option key={w.id} value={w.id}>{w.name}</option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label htmlFor="delivery_date" className="form-label">
                            Delivery Date <span className="text-red-500">*</span>
                        </label>
                        <input
                            id="delivery_date" className="form-input" type="date" required
                            value={values.delivery_date}
                            onChange={(e) => setField('delivery_date', e.target.value)}
                        />
                    </div>

                    <div>
                        <label htmlFor="notes" className="form-label">Notes</label>
                        <textarea
                            id="notes" className="form-textarea" rows={2}
                            value={values.notes}
                            onChange={(e) => setField('notes', e.target.value)}
                        />
                    </div>
                </div>
            </div>

            <div className="card card-body mb-4">
                <h2 className="text-base font-semibold text-gray-700 mb-4">Items to Deliver</h2>
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-gray-200">
                                <th className="pb-2 text-left font-semibold text-gray-600">Product</th>
                                <th className="pb-2 text-left font-semibold text-gray-600" style={{ width: 112 }}>Outstanding</th>
                                <th className="pb-2 text-left font-semibold text-gray-600" style={{ width: 112 }}>Deliver Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            {!order && (
                                <tr>
                                    <td colSpan={3} className="py-4 text-center text-gray-400">Select a Sales Order to load items.</td>
                                </tr>
                            )}
                            {order && deliverable.length === 0 && (
                                <tr>
                                    <td colSpan={3} className="py-4 text-center text-gray-400">
                                        Every line on this order has already been delivered.
                                    </td>
                                </tr>
                            )}
                            {deliverable.map((line, index) => (
                                <tr key={line.item_id} className="border-b border-gray-100">
                                    <td className="py-2 pr-2 text-gray-800">{line.item_name}</td>
                                    <td className="py-2 pr-2 text-gray-500">
                                        {line.outstanding} of {line.quantity}
                                    </td>
                                    <td className="py-2 pr-2">
                                        <input
                                            type="number" step="0.01" min="0" max={line.outstanding}
                                            aria-label={`Quantity for ${line.item_name}`}
                                            value={quantities[line.item_id] ?? ''}
                                            onChange={(e) => setQuantities((prev) => ({ ...prev, [line.item_id]: e.target.value }))}
                                            className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400"
                                        />
                                        {errors[`items.${index}.quantity`] && (
                                            <p className="text-sm text-red-600 mt-1">{errors[`items.${index}.quantity`]}</p>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="flex items-center gap-3">
                <button type="submit" className="btn-primary" disabled={saving}>
                    {saving ? 'Creating…' : 'Create Delivery Note'}
                </button>
                <button type="button" onClick={onCancel} className="btn-secondary">Cancel</button>
            </div>
        </form>
    );
}
