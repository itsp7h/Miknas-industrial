import { useEffect, useMemo, useState } from 'react';
import useViewport from '../../../hooks/useViewport';
import FormModal, { Field, FormSection, fieldErrors, messagesFrom } from '../../ui/FormModal';
import GrnItemRows from './GrnItemRows';
import { CREATE_CHROME } from './grnModalChrome';
import { apiGet, apiPost } from '../../../api/client';

/**
 * The goods receipt form, in the same dialog as the other purchase forms
 * (ui/FormModal). Picking a purchase order loads its lines, as the Blade
 * page's data-items JSON blob did; each line's received quantity defaults to
 * what is still outstanding.
 *
 * A GRN is only ever created, never edited — confirming one raises stock, so
 * there is no edit chrome to pair with the create one.
 */
export default function GrnModal({ presetOrderId, onSaved, onCancel }) {
    const chrome = CREATE_CHROME;
    const compact = useViewport() === 'mobile';

    const [options, setOptions] = useState({ purchase_orders: [], warehouses: [], types: [] });
    const [values, setValues] = useState(() => ({
        purchase_order_id: presetOrderId ? String(presetOrderId) : '',
        warehouse_id: '',
        received_date: new Date().toISOString().slice(0, 10),
        notes: '',
    }));
    const [lines, setLines] = useState([]);
    const [errors, setErrors] = useState({});
    const [messages, setMessages] = useState([]);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        apiGet('/purchase/grns/form-options')
            .then(setOptions)
            // A refusal is not a breakage. 403 here means this account may see
            // goods receipts but not raise them, and saying "could not be
            // loaded" sent people looking for a fault that was not there.
            .catch((err) => setMessages([
                err?.status === 403 || /permission|unauthor/i.test(err?.message ?? '')
                    ? 'You do not have permission to create goods receipts.'
                    : 'The purchase order and warehouse lists could not be loaded.',
            ]));
    }, []);

    const selectedOrder = useMemo(
        () => options.purchase_orders.find((po) => String(po.id) === String(values.purchase_order_id)) ?? null,
        [options.purchase_orders, values.purchase_order_id]
    );

    // Where this order's goods land, when its company says so in Settings.
    const impliedWarehouseId = selectedOrder?.warehouse_id ?? null;

    // Load the chosen order's lines, defaulting each to what is still outstanding.
    useEffect(() => {
        if (!selectedOrder) {
            setLines([]);

            return;
        }
        // The company's link decides the warehouse, so picking the order fills
        // it in. An order whose company has no link leaves whatever is there:
        // it is then an ordinary choice again, not a stale one.
        if (impliedWarehouseId) {
            setValues((prev) => ({ ...prev, warehouse_id: String(impliedWarehouseId) }));
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
    }, [selectedOrder, impliedWarehouseId]);

    function setField(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
        setErrors((prev) => (prev[name] ? { ...prev, [name]: undefined } : prev));
        setMessages([]);
    }

    async function submit(event) {
        event.preventDefault();
        if (saving) return;

        setSaving(true);
        setErrors({});
        setMessages([]);

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
        } catch (rejection) {
            setErrors(fieldErrors(rejection));
            setMessages(messagesFrom(rejection, 'The goods receipt note could not be saved. Please try again.'));
        } finally {
            setSaving(false);
        }
    }

    const field = (props) => (
        <Field idPrefix="grn" values={values} errors={errors} onChange={setField} {...props} />
    );

    return (
        <FormModal
            title={chrome.title} subtitle={chrome.subtitle} gradient={chrome.gradient}
            accent={chrome.accent} icon={chrome.icon} submitLabel={chrome.submitLabel}
            submitting={saving} formId="grn-form" messages={messages} onClose={onCancel}
        >
            <form id="grn-form" onSubmit={submit}>
                <FormSection accent={chrome.accent} title="Receipt Details">
                    <div style={{
                        display: 'grid',
                        gridTemplateColumns: compact ? '1fr' : 'repeat(3,minmax(0,1fr))',
                        gap: '1rem',
                    }}>
                        {field({
                            label: 'Purchase Order', name: 'purchase_order_id', required: true,
                            children: (
                                <select
                                    id="grn-purchase_order_id" name="purchase_order_id" required
                                    className={`form-select${errors.purchase_order_id ? ' form-input-error' : ''}`}
                                    value={values.purchase_order_id}
                                    onChange={(e) => setField('purchase_order_id', e.target.value)}
                                >
                                    <option value="">— Select Purchase Order —</option>
                                    {options.purchase_orders.map((po) => (
                                        <option key={po.id} value={po.id}>
                                            {po.po_number}{po.supplier_name ? ` - ${po.supplier_name}` : ''}
                                        </option>
                                    ))}
                                </select>
                            ),
                        })}

                        {field({
                            label: 'Warehouse', name: 'warehouse_id', required: true,
                            hint: impliedWarehouseId
                                ? `Set by ${selectedOrder?.company_name ?? 'this order\u2019s company'} in Settings \u2192 Company Warehouses.`
                                : 'Where the inventory lines will be raised.',
                            children: (
                                <select
                                    id="grn-warehouse_id" name="warehouse_id" required
                                    className={`form-select${errors.warehouse_id ? ' form-input-error' : ''}`}
                                    value={values.warehouse_id}
                                    onChange={(e) => setField('warehouse_id', e.target.value)}
                                    // The company's warehouse is not a preference to
                                    // override on the day — it is changed in Settings,
                                    // where the decision belongs.
                                    disabled={!!impliedWarehouseId}
                                    style={impliedWarehouseId ? { background: '#f8fafc', color: '#0f172a' } : undefined}
                                >
                                    <option value="">— Select Warehouse —</option>
                                    {options.warehouses.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
                                </select>
                            ),
                        })}

                        {field({ label: 'Received Date', name: 'received_date', type: 'date', required: true })}
                    </div>
                </FormSection>

                <GrnItemRows
                    lines={lines} accent={chrome.accent} compact={compact} errors={errors}
                    hasOrder={!!values.purchase_order_id} onChange={setLines}
                />

                <FormSection accent={chrome.accent} title="Notes" last>
                    {field({ label: 'Notes', name: 'notes', type: 'textarea', rows: 2 })}
                </FormSection>
            </form>
        </FormModal>
    );
}
