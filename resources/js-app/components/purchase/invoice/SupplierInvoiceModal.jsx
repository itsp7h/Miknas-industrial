import { useEffect, useMemo, useState } from 'react';
import useViewport from '../../../hooks/useViewport';
import FormModal, { Field, FormSection, ReadOnlyField, fieldErrors, messagesFrom } from '../../ui/FormModal';
import { CREATE_CHROME, EDIT_CHROME } from './supplierInvoiceModalChrome';
import { apiGet, apiPost, apiPut } from '../../../api/client';
import { money } from './invoiceStyles';

/**
 * The supplier invoice form, in the shared dialog. Total is subtotal + VAT,
 * computed and read-only, as the Blade form's calcInvoiceTotal() did.
 * Supplier, PO and GRN are chosen on create; Status is editable only on an
 * existing invoice, which is what the API enforces (`prohibited` on create).
 *
 * paid_amount is deliberately absent: payments own it, and the payment
 * endpoint resyncs the invoice.
 */
export default function SupplierInvoiceModal({ invoice, onSaved, onCancel }) {
    const isEdit = !!invoice;
    const chrome = isEdit ? EDIT_CHROME : CREATE_CHROME;
    const compact = useViewport() === 'mobile';

    const [options, setOptions] = useState({ suppliers: [], purchase_orders: [], grns: [], statuses: [] });
    const [values, setValues] = useState(() => ({
        supplier_id: invoice?.supplier_id ?? '',
        invoice_number: invoice?.invoice_number ?? '',
        purchase_order_id: invoice?.purchase_order_id ?? '',
        goods_receipt_note_id: invoice?.goods_receipt_note_id ?? '',
        invoice_date: invoice?.invoice_date ?? new Date().toISOString().slice(0, 10),
        due_date: invoice?.due_date ?? '',
        subtotal: invoice?.subtotal ?? '',
        vat_amount: invoice?.vat_amount ?? '0',
        status: invoice?.status ?? 'unpaid',
        notes: invoice?.notes ?? '',
    }));
    const [errors, setErrors] = useState({});
    const [messages, setMessages] = useState([]);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        apiGet('/purchase/invoices/form-options')
            .then(setOptions)
            .catch(() => setMessages(['The supplier, order and GRN lists could not be loaded.']));
    }, []);

    const total = useMemo(
        () => (Number(values.subtotal) || 0) + (Number(values.vat_amount) || 0),
        [values.subtotal, values.vat_amount]
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
            supplier_id: values.supplier_id,
            invoice_number: values.invoice_number,
            purchase_order_id: values.purchase_order_id || null,
            goods_receipt_note_id: values.goods_receipt_note_id || null,
            invoice_date: values.invoice_date,
            due_date: values.due_date || null,
            subtotal: values.subtotal,
            vat_amount: values.vat_amount || 0,
            total_amount: total,
            notes: values.notes || null,
            ...(isEdit ? { status: values.status } : {}),
        };

        try {
            const response = isEdit
                ? await apiPut(`/purchase/invoices/${invoice.id}`, payload)
                : await apiPost('/purchase/invoices', payload);
            onSaved(response.data);
        } catch (rejection) {
            setErrors(fieldErrors(rejection));
            setMessages(messagesFrom(rejection, 'The invoice could not be saved. Please try again.'));
        } finally {
            setSaving(false);
        }
    }

    const field = (props) => (
        <Field idPrefix="invoice" values={values} errors={errors} onChange={setField} {...props} />
    );
    const cols = compact ? '1fr' : 'repeat(3,minmax(0,1fr))';
    const statuses = options.statuses.length ? options.statuses : ['unpaid', 'partial', 'paid'];

    return (
        <FormModal
            title={chrome.title}
            subtitle={isEdit ? (invoice.invoice_number ?? chrome.subtitle) : chrome.subtitle}
            gradient={chrome.gradient} accent={chrome.accent} icon={chrome.icon}
            submitLabel={chrome.submitLabel} submitting={saving} formId="supplier-invoice-form"
            messages={messages} onClose={onCancel}
        >
            <form id="supplier-invoice-form" onSubmit={submit}>
                <FormSection accent={chrome.accent} title="Invoice Details">
                    <div style={{ display: 'grid', gridTemplateColumns: cols, gap: '1rem' }}>
                        {field({ label: 'Invoice Number', name: 'invoice_number', required: true })}

                        {field({
                            label: 'Supplier', name: 'supplier_id', required: true,
                            options: [
                                { value: '', label: '— Select Supplier —' },
                                ...options.suppliers.map((s) => ({ value: s.id, label: s.name })),
                            ],
                        })}

                        {field({ label: 'Invoice Date', name: 'invoice_date', type: 'date', required: true })}
                        {field({
                            label: 'Due Date', name: 'due_date', type: 'date',
                            hint: 'Must be on or after the invoice date.',
                        })}

                        {isEdit && field({
                            label: 'Status', name: 'status',
                            options: statuses.map((s) => ({ value: s, label: s.charAt(0).toUpperCase() + s.slice(1) })),
                            hint: 'Payments move this on their own; set it here only to correct it.',
                        })}
                    </div>
                </FormSection>

                <FormSection accent={chrome.accent} title="Linked Documents">
                    <div style={{ display: 'grid', gridTemplateColumns: compact ? '1fr' : 'repeat(2,minmax(0,1fr))', gap: '1rem' }}>
                        {field({
                            label: 'Purchase Order', name: 'purchase_order_id',
                            hint: 'Optional.',
                            options: [
                                { value: '', label: '— None —' },
                                ...options.purchase_orders.map((po) => ({ value: po.id, label: po.po_number })),
                            ],
                        })}
                        {field({
                            label: 'Goods Receipt Note', name: 'goods_receipt_note_id',
                            hint: 'Optional.',
                            options: [
                                { value: '', label: '— None —' },
                                ...options.grns.map((grn) => ({ value: grn.id, label: grn.grn_number })),
                            ],
                        })}
                    </div>
                </FormSection>

                <FormSection accent={chrome.accent} title="Amounts">
                    <div style={{ display: 'grid', gridTemplateColumns: cols, gap: '1rem' }}>
                        {field({ label: 'Subtotal', name: 'subtotal', type: 'number', required: true })}
                        {field({ label: 'VAT Amount', name: 'vat_amount', type: 'number', required: true })}
                        <ReadOnlyField label="Total Amount" hint="Subtotal plus VAT.">
                            {money(total)}
                        </ReadOnlyField>
                    </div>
                    {errors.total_amount && (
                        <p style={{ marginTop: 4, fontSize: '0.75rem', color: '#dc2626' }}>{errors.total_amount}</p>
                    )}
                </FormSection>

                <FormSection accent={chrome.accent} title="Notes" last>
                    {field({ label: 'Notes', name: 'notes', type: 'textarea', rows: 2 })}
                </FormSection>
            </form>
        </FormModal>
    );
}
