import { useEffect, useMemo, useState } from 'react';
import Button from '../../ui/Button';
import FormField from '../../ui/FormField';
import { apiGet, apiPost } from '../../../api/client';
import { qty } from './grnStyles';

/**
 * Picking a purchase order loads its lines, as the Blade page's data-items JSON
 * blob did. Each line's received quantity defaults to the outstanding amount and
 * carries the Inventory/Consumable toggle.
 */
export default function GrnForm({ presetOrderId, onSaved, onCancel }) {
    const [options, setOptions] = useState({ purchase_orders: [], warehouses: [], types: [] });
    const [values, setValues] = useState(() => ({
        purchase_order_id: presetOrderId ? String(presetOrderId) : '',
        warehouse_id: '',
        received_date: new Date().toISOString().slice(0, 10),
        notes: '',
    }));
    const [lines, setLines] = useState([]);
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        apiGet('/purchase/grns/form-options').then(setOptions).catch(() => {});
    }, []);

    const selectedOrder = useMemo(
        () => options.purchase_orders.find((po) => String(po.id) === String(values.purchase_order_id)) ?? null,
        [options.purchase_orders, values.purchase_order_id]
    );

    // Load the chosen order's lines, defaulting each to what is still outstanding.
    useEffect(() => {
        if (!selectedOrder) {
            setLines([]);

            return;
        }
        setLines(selectedOrder.items.map((line) => {
            const outstanding = Math.max(Number(line.quantity ?? 0) - Number(line.quantity_received ?? 0), 0);

            return {
                purchase_order_item_id: line.purchase_order_item_id,
                item_id: line.item_id,
                item_name: line.item_name,
                quantity: line.quantity,
                unit_cost: line.rate ?? 0,
                quantity_received: String(outstanding > 0 ? outstanding : (line.quantity ?? '')),
                type: 'inventory',
            };
        }));
    }, [selectedOrder]);

    function setField(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
    }

    function setLine(index, name, value) {
        setLines((prev) => prev.map((line, i) => (i === index ? { ...line, [name]: value } : line)));
    }

    async function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        setErrors({});

        try {
            const response = await apiPost('/purchase/grns', {
                ...values,
                notes: values.notes || null,
                items: lines.map((line) => ({
                    item_id: line.item_id,
                    purchase_order_item_id: line.purchase_order_item_id,
                    quantity_received: line.quantity_received,
                    unit_cost: line.unit_cost,
                    type: line.type,
                })),
            });
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
    const lineError = (index, field) => errors[`items.${index}.${field}`];

    const TYPE_STYLE = (active, kind) => ({
        padding: '5px 10px', fontSize: 11, fontWeight: 700, cursor: 'pointer', whiteSpace: 'nowrap',
        background: active ? (kind === 'inventory' ? '#eff6ff' : '#fef3c7') : '#fff',
        color: active ? (kind === 'inventory' ? '#2563eb' : '#92400e') : '#94a3b8',
        ...(kind === 'consumable' ? { borderLeft: '1px solid #e2e8f0' } : {}),
    });

    return (
        <form onSubmit={handleSubmit}>
            <div className="mb-4">
                <label htmlFor="purchase_order_id" className="block text-sm font-medium text-gray-700 mb-1">
                    Purchase Order
                </label>
                <select
                    id="purchase_order_id"
                    value={values.purchase_order_id}
                    onChange={(e) => setField('purchase_order_id', e.target.value)}
                    className={selectClass(errors.purchase_order_id)}
                >
                    <option value="">Select a purchase order…</option>
                    {options.purchase_orders.map((po) => (
                        <option key={po.id} value={po.id}>
                            {po.po_number}{po.supplier_name ? ` - ${po.supplier_name}` : ''}
                        </option>
                    ))}
                </select>
                {errors.purchase_order_id && <p className="text-sm text-red-600 mt-1">{errors.purchase_order_id}</p>}
            </div>

            <div className="mb-4">
                <label htmlFor="warehouse_id" className="block text-sm font-medium text-gray-700 mb-1">Warehouse</label>
                <select
                    id="warehouse_id"
                    value={values.warehouse_id}
                    onChange={(e) => setField('warehouse_id', e.target.value)}
                    className={selectClass(errors.warehouse_id)}
                >
                    <option value="">Select a warehouse…</option>
                    {options.warehouses.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
                </select>
                {errors.warehouse_id && <p className="text-sm text-red-600 mt-1">{errors.warehouse_id}</p>}
            </div>

            <FormField
                label="Received Date" name="received_date" type="date"
                value={values.received_date} onChange={setField} error={errors.received_date}
            />

            <div className="mb-2">
                <span className="text-sm font-medium text-gray-700">Items Received</span>
            </div>
            {errors.items && <p className="text-sm text-red-600 mb-2">{errors.items}</p>}

            {lines.length === 0 && (
                <p style={{ padding: '16px 0', textAlign: 'center', color: '#94a3b8', fontSize: 13 }}>
                    {values.purchase_order_id ? 'No items on this purchase order.' : 'Select a purchase order to load items.'}
                </p>
            )}

            {lines.map((line, index) => (
                <div key={line.purchase_order_item_id ?? index} style={{ border: '1px solid #e2e8f0', borderRadius: 8, padding: 10, marginBottom: 8 }}>
                    <div style={{ fontWeight: 600, color: '#0f172a', fontSize: 13 }}>{line.item_name}</div>
                    <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', alignItems: 'flex-end', marginTop: 6 }}>
                        <div>
                            <div className="text-xs text-gray-500">PO Qty</div>
                            <div style={{ fontWeight: 600, fontSize: 13 }}>{qty(line.quantity)}</div>
                        </div>
                        <div style={{ flex: '1 1 110px' }}>
                            <label htmlFor={`recv_${index}`} className="block text-xs font-medium text-gray-700 mb-1">
                                Qty Received
                            </label>
                            <input
                                id={`recv_${index}`} type="number" step="0.01" min="0"
                                value={line.quantity_received}
                                onChange={(e) => setLine(index, 'quantity_received', e.target.value)}
                                className={selectClass(lineError(index, 'quantity_received'))}
                            />
                        </div>
                        <div>
                            <div className="text-xs text-gray-500 mb-1">Type</div>
                            <div style={{ display: 'flex', border: '1px solid #e2e8f0', borderRadius: 6, overflow: 'hidden', width: 'fit-content' }}>
                                {['inventory', 'consumable'].map((kind) => (
                                    <span
                                        key={kind}
                                        role="button"
                                        tabIndex={0}
                                        aria-pressed={line.type === kind}
                                        onClick={() => setLine(index, 'type', kind)}
                                        onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') setLine(index, 'type', kind); }}
                                        style={TYPE_STYLE(line.type === kind, kind)}
                                    >
                                        {kind === 'inventory' ? 'Inventory' : 'Consumable'}
                                    </span>
                                ))}
                            </div>
                        </div>
                    </div>
                    {['quantity_received', 'item_id'].map((field) =>
                        lineError(index, field)
                            ? <p key={field} className="text-sm text-red-600 mt-1">{lineError(index, field)}</p>
                            : null
                    )}
                </div>
            ))}

            <FormField label="Notes" name="notes" type="textarea" value={values.notes} onChange={setField} error={errors.notes} />

            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                <Button variant="secondary" onClick={onCancel}>Cancel</Button>
                <Button type="submit" loading={saving}>Save GRN</Button>
            </div>
        </form>
    );
}
