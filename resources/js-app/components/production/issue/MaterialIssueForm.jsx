import { useEffect, useMemo, useState } from 'react';
import { apiGet, apiPost } from '../../../api/client';

const today = () => new Date().toISOString().slice(0, 10);

const NO_OPTIONS = { production_orders: [], warehouses: [], stock: [] };

/**
 * Blade kept this form inline under the table rather than behind a modal, laid
 * out two-up inside a `card card-body max-w-2xl`. The material select is the one
 * departure: Blade offered every item in the system, so it was easy to issue
 * something the warehouse did not hold and get the request refused — this offers
 * only what is actually on hand in the chosen warehouse, and says how much.
 */
export default function MaterialIssueForm({ presetOrderId, onSaved }) {
    const [options, setOptions] = useState(NO_OPTIONS);
    const [values, setValues] = useState({
        production_order_id: presetOrderId ?? '',
        item_id: '',
        warehouse_id: '',
        quantity: '',
        issue_date: today(),
        notes: '',
    });
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        // Merged over the empty shape so a partial response cannot leave a list
        // undefined and take the whole form down.
        apiGet('/production/material-issues/form-options')
            .then((response) => setOptions({ ...NO_OPTIONS, ...response }))
            .catch(() => {});
    }, []);

    const issuableStock = useMemo(
        () => (options.stock ?? []).filter((row) =>
            String(row.warehouse_id) === String(values.warehouse_id) && Number(row.quantity) > 0),
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
            const response = await apiPost('/production/material-issues', values);
            onSaved(response.data);
            setValues((prev) => ({
                ...prev, item_id: '', quantity: '', notes: '', issue_date: today(),
            }));
        } catch (err) {
            setErrors(Object.fromEntries(
                Object.entries(err.errors ?? {}).map(([key, messages]) => [key, messages[0]])
            ));
        } finally {
            setSaving(false);
        }
    }

    const messages = Object.values(errors);

    return (
        <div>
            {messages.length > 0 && (
                <div className="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                    <ul className="list-disc list-inside space-y-1">
                        {messages.map((message) => <li key={message}>{message}</li>)}
                    </ul>
                </div>
            )}

            <div className="card card-body" style={{ maxWidth: 672 }}>
                <form onSubmit={handleSubmit}>
                    <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label htmlFor="production_order_id" className="form-label">
                                Production Order <span className="text-red-500">*</span>
                            </label>
                            <select
                                id="production_order_id" className="form-select" required
                                value={values.production_order_id}
                                onChange={(e) => setField('production_order_id', e.target.value)}
                            >
                                <option value="">-- Select Order --</option>
                                {options.production_orders.map((order) => (
                                    <option key={order.id} value={order.id}>
                                        {order.order_number}{order.product_name ? ` - ${order.product_name}` : ''}
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
                                {options.warehouses.map((warehouse) => (
                                    <option key={warehouse.id} value={warehouse.id}>{warehouse.name}</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label htmlFor="item_id" className="form-label">
                                Item <span className="text-red-500">*</span>
                            </label>
                            <select
                                id="item_id" className="form-select" required
                                value={values.item_id}
                                onChange={(e) => setField('item_id', e.target.value)}
                            >
                                <option value="">
                                    {values.warehouse_id ? '-- Select Item --' : '-- Choose a warehouse first --'}
                                </option>
                                {issuableStock.map((row) => (
                                    <option key={row.item_id} value={row.item_id}>
                                        {row.item_name} ({row.quantity} on hand)
                                    </option>
                                ))}
                            </select>
                            {onHand !== undefined && (
                                <p style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                                    {onHand} on hand in this warehouse.
                                </p>
                            )}
                        </div>

                        <div>
                            <label htmlFor="quantity" className="form-label">
                                Quantity <span className="text-red-500">*</span>
                            </label>
                            <input
                                id="quantity" className="form-input" type="number" min="0.001" step="0.001" required
                                value={values.quantity}
                                onChange={(e) => setField('quantity', e.target.value)}
                            />
                        </div>

                        <div>
                            <label htmlFor="issue_date" className="form-label">
                                Issue Date <span className="text-red-500">*</span>
                            </label>
                            <input
                                id="issue_date" className="form-input" type="date" required
                                value={values.issue_date}
                                onChange={(e) => setField('issue_date', e.target.value)}
                            />
                        </div>

                        <div>
                            <label htmlFor="notes" className="form-label">Notes</label>
                            <input
                                id="notes" className="form-input" type="text"
                                value={values.notes}
                                onChange={(e) => setField('notes', e.target.value)}
                            />
                        </div>
                    </div>

                    <div className="mt-6">
                        <button type="submit" className="btn-primary" disabled={saving}>
                            {saving ? 'Issuing…' : 'Issue Material'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
