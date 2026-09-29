import { useEffect, useMemo, useState } from 'react';
import useViewport from '../../../hooks/useViewport';
import FormModal, { Field, FormSection, fieldErrors, messagesFrom } from '../../ui/FormModal';
import GrnItemRows from './GrnItemRows';
import { CREATE_CHROME } from './grnModalChrome';
import { apiGet, apiPostForm } from '../../../api/client';

/** The paperwork a receipt is recorded against, as the API names each file. */
export const DOCUMENTS = [
    { name: 'lpo_document', label: 'LPO' },
    { name: 'grn_document', label: 'GRN' },
    { name: 'tax_invoice_document', label: 'Tax Invoice' },
];

const ACCEPT = '.pdf,.jpg,.jpeg,.png';
// The API's max:10240. Checked here too, so a scan that is too big is named
// at once instead of after the whole upload.
const MAX_BYTES = 10 * 1024 * 1024;
// Optional extra files beside the three, as many as the API takes.
const MAX_OTHER = 5;

/**
 * The payload as multipart form data, since it carries files: nested values
 * flatten to `items[0][item_id]`, which Laravel reads back as the same array.
 */
function toFormData(payload, files) {
    const form = new FormData();
    const append = (key, value) => {
        if (value === null || value === undefined) return;
        if (typeof value === 'object') {
            Object.entries(value).forEach(([k, v]) => append(`${key}[${k}]`, v));
        } else {
            form.append(key, value);
        }
    };
    Object.entries(payload).forEach(([key, value]) => append(key, value));
    Object.entries(files).forEach(([key, file]) => {
        if (Array.isArray(file)) file.forEach((each) => form.append(`${key}[]`, each));
        else if (file) form.append(key, file);
    });

    return form;
}

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
    const [files, setFiles] = useState({});
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

    /** The optional Other files: any number up to MAX_OTHER, each within MAX_BYTES. */
    function setOthers(list, input) {
        setMessages([]);
        const chosen = Array.from(list ?? []);
        const problem = chosen.length > MAX_OTHER
            ? `Attach at most ${MAX_OTHER} other files.`
            : chosen.some((file) => file.size > MAX_BYTES) ? 'Each file must be 10 MB or smaller.' : null;
        if (problem) {
            if (input) input.value = '';
            setFiles((prev) => ({ ...prev, other_documents: [] }));
            setErrors((prev) => ({ ...prev, other_documents: problem }));

            return;
        }
        setFiles((prev) => ({ ...prev, other_documents: chosen }));
        setErrors((prev) => (prev.other_documents ? { ...prev, other_documents: undefined } : prev));
    }

    function setFile(name, file, input) {
        setMessages([]);
        if (file && file.size > MAX_BYTES) {
            if (input) input.value = '';
            setFiles((prev) => ({ ...prev, [name]: null }));
            setErrors((prev) => ({ ...prev, [name]: 'This file is larger than 10 MB.' }));

            return;
        }
        setFiles((prev) => ({ ...prev, [name]: file ?? null }));
        setErrors((prev) => (prev[name] ? { ...prev, [name]: undefined } : prev));
    }

    async function submit(event) {
        event.preventDefault();
        if (saving) return;

        setSaving(true);
        setErrors({});
        setMessages([]);

        try {
            const response = await apiPostForm('/purchase/grns', toFormData({
                ...values,
                notes: values.notes || null,
                items: lines.map((line) => ({
                    item_id: line.item_id,
                    purchase_order_item_id: line.purchase_order_item_id,
                    quantity_received: line.quantity_received,
                    unit_cost: line.unit_cost,
                    type: line.type,
                })),
            }, files));
            onSaved(response.data);
        } catch (rejection) {
            setErrors(fieldErrors(rejection));
            setMessages(messagesFrom(rejection, 'The goods receipt note could not be saved. Please try again.'));
        } finally {
            setSaving(false);
        }
    }

    // The API keys a bad file by its position (other_documents.2); the form
    // has one input for them all, so any of those lands under it.
    const otherError = errors.other_documents
        ?? Object.entries(errors).find(([key, value]) => key.startsWith('other_documents.') && value)?.[1];

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

                <FormSection accent={chrome.accent} title="Documents">
                    <p style={{ margin: '0 0 0.75rem', fontSize: '0.75rem', color: '#64748b' }}>
                        All three are needed to confirm the GRN. Missing one? Save now and upload it later from the GRN&rsquo;s page.
                    </p>
                    <div style={{
                        display: 'grid',
                        gridTemplateColumns: compact ? '1fr' : 'repeat(3,minmax(0,1fr))',
                        gap: '1rem',
                    }}>
                        {DOCUMENTS.map((doc) => (
                            <Field
                                key={doc.name} idPrefix="grn" values={values} errors={errors} onChange={setField}
                                label={doc.label} name={doc.name} required hint="PDF, JPG or PNG, up to 10 MB."
                            >
                                {/* Marked required, but not enforced here: the receipt can
                                    be saved without it and completed once it is uploaded. */}
                                <input
                                    id={`grn-${doc.name}`} name={doc.name} type="file" accept={ACCEPT}
                                    aria-invalid={errors[doc.name] ? true : undefined}
                                    className={`form-input${errors[doc.name] ? ' form-input-error' : ''}`}
                                    onChange={(e) => setFile(doc.name, e.target.files?.[0], e.target)}
                                />
                            </Field>
                        ))}
                    </div>

                    <div style={{ marginTop: '1rem' }}>
                        <Field
                            idPrefix="grn" values={values} onChange={setField}
                            errors={{ other_documents: otherError }}
                            label="Other" name="other_documents"
                            hint={`Optional. Anything else that came with the delivery — up to ${MAX_OTHER} files, PDF, JPG or PNG, 10 MB each.`}
                        >
                            <input
                                id="grn-other_documents" name="other_documents" type="file" accept={ACCEPT} multiple
                                aria-invalid={otherError ? true : undefined}
                                className={`form-input${otherError ? ' form-input-error' : ''}`}
                                onChange={(e) => setOthers(e.target.files, e.target)}
                            />
                        </Field>
                    </div>
                </FormSection>

                <FormSection accent={chrome.accent} title="Notes" last>
                    {field({ label: 'Notes', name: 'notes', type: 'textarea', rows: 2 })}
                </FormSection>
            </form>
        </FormModal>
    );
}
