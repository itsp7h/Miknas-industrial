import { useState } from 'react';
import useViewport from '../../../hooks/useViewport';
import FormModal, { FormSection } from '../../ui/FormModal';
import { apiPost, apiPut } from '../../../api/client';
import { CREATE_CHROME, EDIT_CHROME } from './supplierModalChrome';

const BLANK = {
    name: '',
    supplier_code: '',
    category: '',
    contact_person: '',
    email: '',
    secondary_email: '',
    phone: '',
    phone2: '',
    whatsapp_number: '',
    whatsapp: '',
    website: '',
    address: '',
    tax_number: '',
    credit_days: '',
    credit_terms: '',
    remarks: '',
    is_active: true,
};

// Coerce null/undefined field values (e.g. from a partial or freshly-fetched
// supplier record) to '' so every text input stays controlled from the start —
// React warns ("value prop on input should not be null") and briefly renders
// an uncontrolled input otherwise. `is_active` is left as a real boolean since
// it drives a checkbox's `checked`, not an input's `value`.
function normalize(supplier) {
    const merged = { ...BLANK, ...supplier };

    return Object.fromEntries(
        Object.entries(merged).map(([key, value]) => {
            if (key === 'is_active') return [key, value ?? true];

            return [key, value ?? ''];
        })
    );
}

/** Flattens a Laravel 422 body into the bullet list the MPR modal renders. */
function messagesFrom(rejection) {
    const fromErrors = Object.values(rejection?.errors ?? {}).flat();

    return fromErrors.length
        ? fromErrors
        : [rejection?.message || 'The supplier could not be saved. Please try again.'];
}

function Field({ label, name, values, errors, onChange, type = 'text', required = false, placeholder, hint, options, span = 1, rows = 3 }) {
    const error = errors[name];
    const shared = {
        id: `supplier-${name}`,
        name,
        value: values[name] ?? '',
        placeholder,
        'aria-invalid': error ? true : undefined,
        onChange: (e) => onChange(name, e.target.value),
        className: `form-input${error ? ' form-input-error' : ''}`,
    };

    return (
        <div style={{ gridColumn: span > 1 ? `span ${span}` : undefined }}>
            <label className="form-label" htmlFor={shared.id}>
                {label} {required && <span className="text-red-500">*</span>}
            </label>

            {options ? (
                <select {...shared} className={`form-select${error ? ' form-input-error' : ''}`}>
                    {options.map((option) => (
                        <option key={option.value} value={option.value}>{option.label}</option>
                    ))}
                </select>
            ) : type === 'textarea' ? (
                <textarea {...shared} rows={rows} className={`form-textarea${error ? ' form-input-error' : ''}`} />
            ) : (
                <input {...shared} type={type} />
            )}

            {error && <p style={{ marginTop: 4, fontSize: '0.75rem', color: '#dc2626' }}>{error}</p>}
            {!error && hint && (
                <p style={{ marginTop: 4, fontSize: '0.7rem', color: '#94a3b8' }}>{hint}</p>
            )}
        </div>
    );
}

/**
 * `hasCredit()` in supplierStats — which decides whether the list badges a
 * supplier as having credit — recognises only "y" and "yes". This was a
 * free-text box labelled "Credit (Y/N)", so anything else typed into it
 * (a term like "Net 30", say) saved happily and then silently failed to
 * count. A select can only produce what the reader understands.
 */
const CREDIT_OPTIONS = [
    { value: '', label: '— Not specified —' },
    { value: 'yes', label: 'Yes' },
    { value: 'no', label: 'No' },
];

/**
 * The supplier form, drawn in the same dialog as the MPR form: gradient
 * header, titled section cards with an accent bar, `.form-input` controls,
 * and a pinned footer (ui/FormModal). It used to be a bare `<div>` of
 * sixteen stacked fields inside the small generic `ui/Modal`, with unstyled
 * buttons — the one part of the Suppliers page the React cutover left
 * un-ported.
 *
 * One tree with a `compact` flag rather than a desktop/mobile pair, for the
 * same reason the MPR modal is (CLAUDE.md #10): two copies of a form this
 * long would drift.
 */
export default function SupplierModal({ supplier, onSaved, onCancel }) {
    const [values, setValues] = useState(() => normalize(supplier));
    const [errors, setErrors] = useState({});
    const [messages, setMessages] = useState([]);
    const [saving, setSaving] = useState(false);
    const compact = useViewport() === 'mobile';

    const chrome = supplier ? EDIT_CHROME : CREATE_CHROME;
    const cols = compact ? '1fr' : 'repeat(2,minmax(0,1fr))';

    function set(name, value) {
        setValues((current) => ({ ...current, [name]: value }));
        // Clear the field's error the moment it is edited, so a message about
        // the old value does not sit under the new one.
        setErrors((current) => (current[name] ? { ...current, [name]: undefined } : current));
        setMessages([]);
    }

    async function submit(event) {
        event.preventDefault();
        if (saving) return;

        if (!values.name.trim()) {
            setErrors({ name: 'Name is required.' });
            setMessages(['Supplier name is required.']);

            return;
        }

        setErrors({});
        setMessages([]);
        setSaving(true);

        try {
            const response = supplier
                ? await apiPut(`/purchase/suppliers/${supplier.id}`, values)
                : await apiPost('/purchase/suppliers', values);
            onSaved(response.data);
        } catch (rejection) {
            setErrors(
                Object.fromEntries(
                    Object.entries(rejection?.errors ?? {}).map(([key, list]) => [
                        key, Array.isArray(list) ? list[0] : String(list),
                    ])
                )
            );
            // Anything that is not a 422 used to clear the error state and
            // show nothing at all — the dialog sat there as though Save had
            // never been clicked.
            setMessages(messagesFrom(rejection));
        } finally {
            setSaving(false);
        }
    }

    const field = (props) => (
        <Field values={values} errors={errors} onChange={set} {...props} />
    );

    return (
        <FormModal
            title={chrome.title} subtitle={chrome.subtitle} gradient={chrome.gradient}
            accent={chrome.accent} icon={chrome.icon} submitLabel={chrome.submitLabel}
            submitting={saving} formId="supplier-form" messages={messages}
            onClose={onCancel} maxWidth="48rem"
        >
            <form id="supplier-form" onSubmit={submit}>
                <FormSection accent={chrome.accent} title="Company">
                    <div style={{ display: 'grid', gridTemplateColumns: cols, gap: '1rem' }}>
                        {field({ label: 'Supplier Name', name: 'name', required: true, span: compact ? 1 : 2 })}
                        {field({ label: 'Supplier Code', name: 'supplier_code' })}
                        {field({ label: 'Category', name: 'category', placeholder: 'e.g. Steel' })}
                    </div>

                    <label
                        htmlFor="supplier-is_active"
                        style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: '1rem', fontSize: '0.85rem', fontWeight: 500, color: '#334155' }}
                    >
                        <input
                            id="supplier-is_active" name="is_active" type="checkbox"
                            checked={!!values.is_active}
                            onChange={(e) => set('is_active', e.target.checked)}
                            style={{ width: 16, height: 16, accentColor: chrome.accent, cursor: 'pointer' }}
                        />
                        Active
                    </label>
                </FormSection>

                <FormSection accent={chrome.accent} title="Contact">
                    <div style={{ display: 'grid', gridTemplateColumns: cols, gap: '1rem' }}>
                        {field({ label: 'Contact Person', name: 'contact_person', span: compact ? 1 : 2 })}
                        {field({ label: 'Email', name: 'email', type: 'email' })}
                        {field({ label: 'Secondary Email', name: 'secondary_email', type: 'email' })}
                        {field({ label: 'Phone', name: 'phone' })}
                        {field({ label: 'Phone 2', name: 'phone2' })}

                        {/* These two labels used to read "WhatsApp Number" and
                            "WhatsApp (secondary)", which had it backwards. The
                            Excel import writes `whatsapp` and the list links
                            it; only `whatsapp_number` is what
                            Supplier::routeNotificationForWhatsApp returns, so
                            it alone decides whether the supplier is ever
                            actually messaged. Say which is which. */}
                        {field({
                            label: 'WhatsApp (notifications)', name: 'whatsapp_number',
                            placeholder: '+97317701234',
                            hint: 'International format. LPO and RFQ messages go here.',
                        })}
                        {field({
                            label: 'WhatsApp (directory)', name: 'whatsapp',
                            placeholder: '+97317701234',
                            hint: 'Shown on the supplier list. Filled in by the Excel import.',
                        })}

                        {field({ label: 'Website', name: 'website', placeholder: 'https://…', span: compact ? 1 : 2 })}
                        {field({ label: 'Address', name: 'address', type: 'textarea', rows: 2, span: compact ? 1 : 2 })}
                    </div>
                </FormSection>

                <FormSection accent={chrome.accent} title="Commercial" last={false}>
                    <div style={{ display: 'grid', gridTemplateColumns: compact ? '1fr' : 'repeat(3,minmax(0,1fr))', gap: '1rem' }}>
                        {field({ label: 'Tax Number', name: 'tax_number' })}
                        {field({ label: 'Credit', name: 'credit_terms', options: CREDIT_OPTIONS })}
                        {field({ label: 'Credit Days', name: 'credit_days', type: 'number' })}
                    </div>
                </FormSection>

                <FormSection accent={chrome.accent} title="Remarks / Notes" last>
                    {field({ label: 'Remarks', name: 'remarks', type: 'textarea', rows: 2 })}
                </FormSection>
            </form>
        </FormModal>
    );
}
