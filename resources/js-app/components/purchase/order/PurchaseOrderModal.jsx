import { useEffect, useState } from 'react';
import useViewport from '../../../hooks/useViewport';
import FormModal, { Field, FormSection, fieldErrors, messagesFrom } from '../../ui/FormModal';
import OrderItemRows, { blankLine } from './OrderItemRows';
import { CREATE_CHROME, EDIT_CHROME } from './purchaseOrderModalChrome';
import { apiGet, apiPost, apiPut } from '../../../api/client';

/**
 * The purchase order form, in the same dialog as the MPR and supplier forms
 * (ui/FormModal): gradient header, titled section cards, `.form-input`
 * controls, pinned footer. It was a bare form of stacked fields with
 * bordered flex-wrap cards for the line items and a green `ui/Button`
 * primary — the green-where-Blade-was-blue symptom CLAUDE.md #12 describes.
 *
 * Create collects the header plus line items, as the Blade create page did.
 * Edit is header-only and adds Status: the Blade edit form had no line-item
 * section and the API update endpoint mirrors that scope.
 */
export default function PurchaseOrderModal({ order, onSaved, onCancel }) {
    const isEdit = !!order;
    const chrome = isEdit ? EDIT_CHROME : CREATE_CHROME;
    const compact = useViewport() === 'mobile';

    const [options, setOptions] = useState({ suppliers: [], items: [], purchase_requests: [], statuses: [] });
    const [values, setValues] = useState(() => ({
        supplier_id: order?.supplier_id ?? '',
        purchase_request_id: order?.purchase_request_id ?? '',
        po_date: order?.po_date ?? new Date().toISOString().slice(0, 10),
        expected_delivery_date: order?.expected_delivery_date ?? '',
        status: order?.status ?? 'draft',
        notes: order?.notes ?? '',
    }));
    const [lines, setLines] = useState([blankLine()]);
    const [errors, setErrors] = useState({});
    const [messages, setMessages] = useState([]);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        apiGet('/purchase/orders/form-options')
            .then(setOptions)
            .catch(() => setMessages(['The supplier and item lists could not be loaded.']));
    }, []);

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
        } catch (rejection) {
            setErrors(fieldErrors(rejection));
            // Anything that is not a 422 used to leave the dialog silent.
            setMessages(messagesFrom(rejection, 'The purchase order could not be saved. Please try again.'));
        } finally {
            setSaving(false);
        }
    }

    const statuses = options.statuses.length ? options.statuses : ['draft', 'sent', 'received', 'cancelled'];
    const cols = compact ? '1fr' : 'repeat(3,minmax(0,1fr))';
    const selectClass = (error) => `form-select${error ? ' form-input-error' : ''}`;

    return (
        <FormModal
            title={chrome.title}
            subtitle={isEdit ? (order.po_number ?? chrome.subtitle) : chrome.subtitle}
            gradient={chrome.gradient} accent={chrome.accent} icon={chrome.icon}
            submitLabel={chrome.submitLabel} submitting={saving} formId="purchase-order-form"
            messages={messages} onClose={onCancel}
        >
            <form id="purchase-order-form" onSubmit={submit}>
                <FormSection accent={chrome.accent} title="Order Details">
                    <div style={{ display: 'grid', gridTemplateColumns: cols, gap: '1rem' }}>
                        <Field idPrefix="po" name="supplier_id" label="Supplier" required values={values} errors={errors} onChange={setField}>
                            <select
                                id="po-supplier_id" required className={selectClass(errors.supplier_id)}
                                value={values.supplier_id}
                                onChange={(e) => setField('supplier_id', e.target.value)}
                            >
                                <option value="">— Select Supplier —</option>
                                {options.suppliers.map((supplier) => (
                                    <option key={supplier.id} value={supplier.id}>{supplier.name}</option>
                                ))}
                            </select>
                        </Field>

                        {!isEdit && (
                            <Field
                                idPrefix="po" name="purchase_request_id" label="Purchase Request"
                                values={values} errors={errors} onChange={setField}
                                hint="Optional — links this order back to an approved request."
                            >
                                <select
                                    id="po-purchase_request_id" className={selectClass(errors.purchase_request_id)}
                                    value={values.purchase_request_id}
                                    onChange={(e) => setField('purchase_request_id', e.target.value)}
                                >
                                    <option value="">— None —</option>
                                    {options.purchase_requests.map((pr) => (
                                        <option key={pr.id} value={pr.id}>{pr.request_number ?? `#${pr.id}`}</option>
                                    ))}
                                </select>
                            </Field>
                        )}

                        <Field idPrefix="po" name="po_date" label="PO Date" required values={values} errors={errors} onChange={setField}>
                            <input
                                id="po-po_date" type="date" required
                                className={`form-input${errors.po_date ? ' form-input-error' : ''}`}
                                value={values.po_date}
                                onChange={(e) => setField('po_date', e.target.value)}
                            />
                        </Field>

                        <Field
                            idPrefix="po" name="expected_delivery_date" label="Expected Delivery Date"
                            values={values} errors={errors} onChange={setField}
                        >
                            <input
                                id="po-expected_delivery_date" type="date"
                                className={`form-input${errors.expected_delivery_date ? ' form-input-error' : ''}`}
                                value={values.expected_delivery_date}
                                onChange={(e) => setField('expected_delivery_date', e.target.value)}
                            />
                        </Field>

                        {isEdit && (
                            <Field idPrefix="po" name="status" label="Status" values={values} errors={errors} onChange={setField}>
                                <select
                                    id="po-status" className={selectClass(errors.status)}
                                    value={values.status}
                                    onChange={(e) => setField('status', e.target.value)}
                                >
                                    {statuses.map((status) => (
                                        <option key={status} value={status}>
                                            {status.charAt(0).toUpperCase() + status.slice(1)}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        )}
                    </div>
                </FormSection>

                {/* Edit is header-only, matching the Blade edit page and the
                    API's update scope. */}
                {!isEdit && (
                    <OrderItemRows
                        lines={lines} items={options.items} accent={chrome.accent}
                        compact={compact} errors={errors} onChange={setLines}
                    />
                )}

                <FormSection accent={chrome.accent} title="Notes" last>
                    <Field
                        idPrefix="po" name="notes" label="Notes" type="textarea" rows={2}
                        values={values} errors={errors} onChange={setField}
                    />
                </FormSection>
            </form>
        </FormModal>
    );
}
