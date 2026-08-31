import { useEffect, useMemo, useState } from 'react';
import FormField from '../../ui/FormField';
import Button from '../../ui/Button';
import { money } from './statuses';
import { apiGet, apiPost, apiPut } from '../../../api/client';

const emptyLine = () => ({ item_id: '', quantity: '', rate: '' });

/**
 * Create mode collects the header plus line items, matching the Blade create
 * page. Edit mode is header-only — the Blade edit form had no line-item
 * section, and the API update endpoint mirrors that scope.
 */
export default function PurchaseOrderForm({ order, onSaved, onCancel }) {
    const isEdit = !!order;
    const [options, setOptions] = useState({ suppliers: [], items: [], purchase_requests: [], statuses: [] });
    const [values, setValues] = useState(() => ({
        supplier_id: order?.supplier_id ?? '',
        purchase_request_id: order?.purchase_request_id ?? '',
        po_date: order?.po_date ?? new Date().toISOString().slice(0, 10),
        expected_delivery_date: order?.expected_delivery_date ?? '',
        status: order?.status ?? 'draft',
        notes: order?.notes ?? '',
    }));
    const [lines, setLines] = useState([emptyLine()]);
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        apiGet('/purchase/orders/form-options').then(setOptions).catch(() => {});
    }, []);

    const total = useMemo(
        () => lines.reduce((sum, line) => sum + (Number(line.quantity) || 0) * (Number(line.rate) || 0), 0),
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

        const payload = isEdit
            ? {
                supplier_id: values.supplier_id,
                po_date: values.po_date,
                expected_delivery_date: values.expected_delivery_date || null,
                status: values.status,
                notes: values.notes || null,
            }
            : {
                supplier_id: values.supplier_id,
                purchase_request_id: values.purchase_request_id || null,
                po_date: values.po_date,
                expected_delivery_date: values.expected_delivery_date || null,
                notes: values.notes || null,
                items: lines,
            };

        try {
            const response = isEdit
                ? await apiPut(`/purchase/orders/${order.id}`, payload)
                : await apiPost('/purchase/orders', payload);
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

    const selectClass = (error) =>
        `border rounded-md px-3 py-2 text-sm w-full ${error ? 'border-red-400' : 'border-gray-300'}`;

    return (
        <form onSubmit={handleSubmit}>
            <div className="mb-4">
                <label htmlFor="supplier_id" className="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                <select
                    id="supplier_id"
                    value={values.supplier_id}
                    onChange={(e) => setField('supplier_id', e.target.value)}
                    className={selectClass(errors.supplier_id)}
                >
                    <option value="">Select a supplier…</option>
                    {options.suppliers.map((supplier) => (
                        <option key={supplier.id} value={supplier.id}>{supplier.name}</option>
                    ))}
                </select>
                {errors.supplier_id && <p className="text-sm text-red-600 mt-1">{errors.supplier_id}</p>}
            </div>

            {!isEdit && (
                <div className="mb-4">
                    <label htmlFor="purchase_request_id" className="block text-sm font-medium text-gray-700 mb-1">
                        Purchase Request (optional)
                    </label>
                    <select
                        id="purchase_request_id"
                        value={values.purchase_request_id}
                        onChange={(e) => setField('purchase_request_id', e.target.value)}
                        className={selectClass(errors.purchase_request_id)}
                    >
                        <option value="">None</option>
                        {options.purchase_requests.map((pr) => (
                            <option key={pr.id} value={pr.id}>{pr.request_number ?? `#${pr.id}`}</option>
                        ))}
                    </select>
                    {errors.purchase_request_id && <p className="text-sm text-red-600 mt-1">{errors.purchase_request_id}</p>}
                </div>
            )}

            <FormField label="PO Date" name="po_date" type="date" value={values.po_date} onChange={setField} error={errors.po_date} />
            <FormField
                label="Expected Delivery Date" name="expected_delivery_date" type="date"
                value={values.expected_delivery_date} onChange={setField} error={errors.expected_delivery_date}
            />

            {isEdit && (
                <div className="mb-4">
                    <label htmlFor="status" className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select
                        id="status"
                        value={values.status}
                        onChange={(e) => setField('status', e.target.value)}
                        className={selectClass(errors.status)}
                    >
                        {(options.statuses.length ? options.statuses : ['draft', 'sent', 'received', 'cancelled']).map((status) => (
                            <option key={status} value={status}>{status.charAt(0).toUpperCase() + status.slice(1)}</option>
                        ))}
                    </select>
                    {errors.status && <p className="text-sm text-red-600 mt-1">{errors.status}</p>}
                </div>
            )}

            {!isEdit && (
                <>
                    <div className="mb-2 flex items-center justify-between">
                        <span className="text-sm font-medium text-gray-700">Order Items</span>
                        <Button variant="secondary" onClick={addLine}>Add row</Button>
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
                                        className={selectClass(lineError(index, 'item_id'))}
                                    >
                                        <option value="">Select…</option>
                                        {options.items.map((item) => (
                                            <option key={item.id} value={item.id}>
                                                {item.item_code} - {item.item_name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div style={{ flex: '1 1 90px' }}>
                                    <label htmlFor={`qty_${index}`} className="block text-xs font-medium text-gray-700 mb-1">Quantity</label>
                                    <input
                                        id={`qty_${index}`} type="number" step="0.01" min="0" value={line.quantity}
                                        onChange={(e) => setLine(index, 'quantity', e.target.value)}
                                        className={selectClass(lineError(index, 'quantity'))}
                                    />
                                </div>
                                <div style={{ flex: '1 1 90px' }}>
                                    <label htmlFor={`rate_${index}`} className="block text-xs font-medium text-gray-700 mb-1">Rate</label>
                                    <input
                                        id={`rate_${index}`} type="number" step="0.01" min="0" value={line.rate}
                                        onChange={(e) => setLine(index, 'rate', e.target.value)}
                                        className={selectClass(lineError(index, 'rate'))}
                                    />
                                </div>
                                <div style={{ flex: '1 1 90px', textAlign: 'right', paddingBottom: 8 }}>
                                    <div className="text-xs text-gray-500">Line total</div>
                                    <div style={{ fontWeight: 600 }}>
                                        {money((Number(line.quantity) || 0) * (Number(line.rate) || 0))}
                                    </div>
                                </div>
                                {lines.length > 1 && (
                                    <Button variant="link-danger" onClick={() => removeLine(index)}>Remove</Button>
                                )}
                            </div>
                            {['item_id', 'quantity', 'rate'].map((field) =>
                                lineError(index, field) ? (
                                    <p key={field} className="text-sm text-red-600 mt-1">{lineError(index, field)}</p>
                                ) : null
                            )}
                        </div>
                    ))}

                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 12, alignItems: 'baseline', margin: '12px 0' }}>
                        <span className="text-sm text-gray-500">Grand total</span>
                        <span style={{ fontSize: 18, fontWeight: 700 }}>{money(total)}</span>
                    </div>
                </>
            )}

            <FormField label="Notes" name="notes" type="textarea" value={values.notes} onChange={setField} error={errors.notes} />

            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                <Button variant="secondary" onClick={onCancel}>Cancel</Button>
                <Button type="submit" loading={saving}>{isEdit ? 'Update Order' : 'Create Purchase Order'}</Button>
            </div>
        </form>
    );
}
