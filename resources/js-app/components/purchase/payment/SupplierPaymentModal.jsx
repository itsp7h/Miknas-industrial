import { useEffect, useMemo, useState } from 'react';
import useViewport from '../../../hooks/useViewport';
import FormModal, { Field, FormSection, ReadOnlyField, fieldErrors, messagesFrom } from '../../ui/FormModal';
import { CREATE_CHROME, EDIT_CHROME } from './supplierPaymentModalChrome';
import { apiGet, apiPost, apiPut } from '../../../api/client';
import { METHOD_LABELS, money } from './paymentStyles';

/**
 * The supplier payment form, in the shared dialog.
 *
 * The invoice can only be chosen when recording a new payment — moving an
 * existing payment between invoices would need both balances resynced, which
 * the Blade edit form never attempted and the API's update endpoint does not
 * accept. On edit it is shown as a fixed value instead.
 */
export default function SupplierPaymentModal({ payment, presetInvoiceId, onSaved, onCancel }) {
    const isEdit = !!payment;
    const chrome = isEdit ? EDIT_CHROME : CREATE_CHROME;
    const compact = useViewport() === 'mobile';

    const [options, setOptions] = useState({ invoices: [], methods: [] });
    const [values, setValues] = useState(() => ({
        supplier_invoice_id: payment?.supplier_invoice_id ?? (presetInvoiceId ? String(presetInvoiceId) : ''),
        payment_date: payment?.payment_date ?? new Date().toISOString().slice(0, 10),
        amount: payment?.amount ?? '',
        payment_method: payment?.payment_method ?? '',
        reference_number: payment?.reference_number ?? '',
        notes: payment?.notes ?? '',
    }));
    const [errors, setErrors] = useState({});
    const [messages, setMessages] = useState([]);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        apiGet('/purchase/payments/form-options')
            .then(setOptions)
            .catch(() => setMessages(['The list of unpaid invoices could not be loaded.']));
    }, []);

    const selected = useMemo(
        () => options.invoices.find((i) => String(i.id) === String(values.supplier_invoice_id)) ?? null,
        [options.invoices, values.supplier_invoice_id]
    );

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

        const payload = {
            payment_date: values.payment_date,
            amount: values.amount,
            payment_method: values.payment_method,
            reference_number: values.reference_number || null,
            notes: values.notes || null,
            ...(isEdit ? {} : { supplier_invoice_id: values.supplier_invoice_id }),
        };

        try {
            const response = isEdit
                ? await apiPut(`/purchase/payments/${payment.id}`, payload)
                : await apiPost('/purchase/payments', payload);
            onSaved(response.data);
        } catch (rejection) {
            setErrors(fieldErrors(rejection));
            // Overpaying is refused by the API with a plain message rather
            // than a field error, so it has to surface in the summary.
            setMessages(messagesFrom(rejection, 'The payment could not be recorded. Please try again.'));
        } finally {
            setSaving(false);
        }
    }

    const field = (props) => (
        <Field idPrefix="payment" values={values} errors={errors} onChange={setField} {...props} />
    );
    const methods = options.methods.length ? options.methods : Object.keys(METHOD_LABELS);
    const cols = compact ? '1fr' : 'repeat(2,minmax(0,1fr))';

    return (
        <FormModal
            title={chrome.title}
            subtitle={isEdit ? (payment.invoice_number ?? chrome.subtitle) : chrome.subtitle}
            gradient={chrome.gradient} accent={chrome.accent} icon={chrome.icon}
            submitLabel={chrome.submitLabel} submitting={saving} formId="supplier-payment-form"
            messages={messages} onClose={onCancel} maxWidth="46rem"
        >
            <form id="supplier-payment-form" onSubmit={submit}>
                <FormSection accent={chrome.accent} title="Invoice">
                    {isEdit ? (
                        <ReadOnlyField
                            label="Invoice"
                            hint="A payment cannot be moved between invoices — both balances would need resyncing."
                        >
                            {payment.invoice_number}
                            {payment.supplier_name ? ` — ${payment.supplier_name}` : ''}
                        </ReadOnlyField>
                    ) : (
                        <>
                            {field({
                                label: 'Invoice', name: 'supplier_invoice_id', required: true,
                                options: [
                                    { value: '', label: '— Select an unpaid invoice —' },
                                    ...options.invoices.map((invoice) => ({
                                        value: invoice.id,
                                        label: `${invoice.invoice_number}`
                                            + (invoice.supplier_name ? ` - ${invoice.supplier_name}` : '')
                                            + ` (Outstanding: ${money(invoice.outstanding)})`,
                                    })),
                                ],
                            })}

                            {/* Shown so the amount can be judged against what is owed. */}
                            {selected && (
                                <p style={{ marginTop: '0.5rem', fontSize: '0.75rem', color: '#64748b' }}>
                                    Outstanding on this invoice: <strong>{money(selected.outstanding)}</strong>
                                </p>
                            )}
                        </>
                    )}
                </FormSection>

                <FormSection accent={chrome.accent} title="Payment">
                    <div style={{ display: 'grid', gridTemplateColumns: cols, gap: '1rem' }}>
                        {field({ label: 'Payment Date', name: 'payment_date', type: 'date', required: true })}
                        {field({
                            label: 'Amount', name: 'amount', type: 'number', required: true,
                            hint: 'Cannot exceed what is still outstanding.',
                        })}
                        {field({
                            label: 'Payment Method', name: 'payment_method', required: true,
                            options: [
                                { value: '', label: '— Select Method —' },
                                ...methods.map((m) => ({ value: m, label: METHOD_LABELS[m] ?? m })),
                            ],
                        })}
                        {field({
                            label: 'Reference Number', name: 'reference_number',
                            hint: 'Cheque or transfer reference.',
                        })}
                    </div>
                </FormSection>

                <FormSection accent={chrome.accent} title="Notes" last>
                    {field({ label: 'Notes', name: 'notes', type: 'textarea', rows: 2 })}
                </FormSection>
            </form>
        </FormModal>
    );
}
