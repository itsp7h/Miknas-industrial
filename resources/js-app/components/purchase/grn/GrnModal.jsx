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

    const [options, setOptions] = useState({ purchase_orders: [], warehouses: [], projects: [], types: [] });
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

    // What a consumable line may be charged to: the order's company's
    // projects, or all of them when the company is not on file. The MPR's
    // project, when it names one of them, is the default.
    const projects = useMemo(() => {
        const all = options.projects ?? [];
        const own = selectedOrder?.company_id ? all.filter((p) => p.company_id === selectedOrder.company_id) : [];

        return own.length ? own : all;
    }, [options.projects, selectedOrder]);
    const defaultProjectId = String(projects.find((p) => p.name === selectedOrder?.project_name)?.id ?? '');

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
            // Ordered in the supplier's unit: counted in theirs (5 BAG), and
            // converted into ours on the GRN before it is confirmed.
            const theirs = !!line.supplier_unit;
            const ordered = Number((theirs ? line.supplier_quantity : line.quantity) ?? 0);
            const received = Number((theirs ? line.supplier_quantity_received : line.quantity_received) ?? 0);
            const outstanding = Math.max(ordered - received, 0);

            return {
                purchase_order_item_id: line.purchase_order_item_id,
                item_id: line.item_id,
                item_name: line.item_name,
                unit_of_measure: line.unit_of_measure,
                quantity: ordered,
                unit_cost: (theirs ? line.supplier_rate : line.rate) ?? 0,
                supplier_unit: line.supplier_unit ?? null,
                quantity_received: String(outstanding > 0 ? outstanding : (ordered || '')),
                type: 'inventory',
                project_id: '',
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
                    // A line in the supplier's unit says how many of theirs
                    // arrived; the API costs it once it is converted.
                    ...(line.supplier_unit
                        ? { supplier_quantity: line.quantity_received }
                        : { quantity_received: line.quantity_received, unit_cost: line.unit_cost }),
                    type: line.type,
                    project_id: line.type === 'consumable' ? line.project_id : null,
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
                    lines={lines} projects={projects} defaultProjectId={defaultProjectId} accent={chrome.accent} compact={compact} errors={errors}
                    hasOrder={!!values.purchase_order_id} onChange={setLines}
                />

                <FormSection accent={chrome.accent} title="Documents">
                    <p style={{ margin: '0 0 0.75rem', fontSize: '0.75rem', color: '#64748b' }}>
                        All three are needed to confirm the GRN. Missing one? Save now and upload it later from the GRN&rsquo;s page.
                    </p>
                    {compact ? (
                        // The phone's three tiles: tap one to scan or pick the
                        // paper; it turns green once something is attached.
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, minmax(0, 1fr))', gap: 8 }}>
                            {DOCUMENTS.map((doc) => (
                                <DocumentTile
                                    key={doc.name} doc={doc} file={files[doc.name]} error={errors[doc.name]}
                                    onChange={(e) => setFile(doc.name, e.target.files?.[0], e.target)}
                                />
                            ))}
                        </div>
                    ) : (
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
                    )}

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

function DocumentTile({ doc, file, error, onChange }) {
    const attached = !!file;
    const id = `grn-${doc.name}`;

    return (
        <label
            htmlFor={id}
            style={{
                position: 'relative', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 6, padding: '14px 6px',
                borderRadius: 16, background: '#FFFFFF', cursor: 'pointer', textAlign: 'center', minWidth: 0,
                border: `1.5px solid ${error ? '#F87171' : (attached ? '#15803D' : '#E2E8F0')}`,
            }}
        >
            <span style={{
                width: 40, height: 40, borderRadius: 20, display: 'flex', alignItems: 'center', justifyContent: 'center',
                background: attached ? '#15803D' : '#EFF6FF', color: attached ? '#FFFFFF' : '#2563EB',
            }}>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                    {attached
                        ? <path d="m5 12 5 5 9-10" />
                        : <><path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z" /><circle cx="12" cy="13" r="3.5" /></>}
                </svg>
            </span>
            <span style={{ fontSize: 15, fontWeight: 600, color: '#0F172A' }}>{doc.label}</span>
            <span style={{ fontSize: 12, color: error ? '#DC2626' : '#475569', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', maxWidth: '100%' }}>
                {error ?? (attached ? 'Attached' : 'Scan / upload')}
            </span>
            <input
                id={id} name={doc.name} type="file" accept={ACCEPT}
                aria-label={`${doc.label} document`}
                aria-invalid={error ? true : undefined}
                onChange={onChange}
                style={{ position: 'absolute', width: 1, height: 1, opacity: 0, pointerEvents: 'none' }}
            />
        </label>
    );
}
