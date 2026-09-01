import { useEffect, useMemo, useState } from 'react';
import Button from '../../ui/Button';
import FormField from '../../ui/FormField';
import { apiGet, apiPost, apiPut } from '../../../api/client';
import { money } from './invoiceStyles';

/**
 * Total is subtotal + VAT, computed and read-only, as the Blade form's
 * calcInvoiceTotal() did. Supplier/PO/GRN are chosen on create; Status is
 * editable only on an existing invoice, matching the Blade edit form.
 */
export default function SupplierInvoiceForm({ invoice, onSaved, onCancel }) {
    const isEdit = !!invoice;
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
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        apiGet('/purchase/invoices/form-options').then(setOptions).catch(() => {});
    }, []);

    const total = useMemo(
        () => (Number(values.subtotal) || 0) + (Number(values.vat_amount) || 0),
        [values.subtotal, values.vat_amount]
    );

    function setField(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
    }

    async function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        setErrors({});

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
            // paid_amount is deliberately absent — payments own it.
            ...(isEdit ? { status: values.status } : {}),
        };

        try {
            const response = isEdit
                ? await apiPut(`/purchase/invoices/${invoice.id}`, payload)
                : await apiPost('/purchase/invoices', payload);
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

    const Select = ({ name, label, children }) => (
        <div className="mb-4">
            <label htmlFor={name} className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
            <select
                id={name}
                value={values[name]}
                onChange={(e) => setField(name, e.target.value)}
                className={selectClass(errors[name])}
            >
                {children}
            </select>
            {errors[name] && <p className="text-sm text-red-600 mt-1">{errors[name]}</p>}
        </div>
    );

    return (
        <form onSubmit={handleSubmit}>
            <FormField
                label="Invoice Number" name="invoice_number"
                value={values.invoice_number} onChange={setField} error={errors.invoice_number}
            />

            <Select name="supplier_id" label="Supplier">
                <option value="">Select a supplier…</option>
                {options.suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
            </Select>

            <Select name="purchase_order_id" label="Purchase Order (optional)">
                <option value="">None</option>
                {options.purchase_orders.map((po) => <option key={po.id} value={po.id}>{po.po_number}</option>)}
            </Select>

            <Select name="goods_receipt_note_id" label="GRN (optional)">
                <option value="">None</option>
                {options.grns.map((grn) => <option key={grn.id} value={grn.id}>{grn.grn_number}</option>)}
            </Select>

            <FormField label="Invoice Date" name="invoice_date" type="date" value={values.invoice_date} onChange={setField} error={errors.invoice_date} />
            <FormField label="Due Date" name="due_date" type="date" value={values.due_date} onChange={setField} error={errors.due_date} />
            <FormField label="Subtotal" name="subtotal" type="number" value={values.subtotal} onChange={setField} error={errors.subtotal} />
            <FormField label="VAT Amount" name="vat_amount" type="number" value={values.vat_amount} onChange={setField} error={errors.vat_amount} />

            <div className="mb-4">
                <span className="block text-sm font-medium text-gray-700 mb-1">Total Amount</span>
                <div style={{
                    border: '1px solid #e2e8f0', background: '#f8fafc', borderRadius: 8,
                    padding: '8px 12px', fontSize: 14, fontWeight: 600, color: '#1f2937',
                }}>
                    {money(total)}
                </div>
                {errors.total_amount && <p className="text-sm text-red-600 mt-1">{errors.total_amount}</p>}
            </div>

            {isEdit && (
                <Select name="status" label="Status">
                    {(options.statuses.length ? options.statuses : ['unpaid', 'partial', 'paid']).map((s) => (
                        <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
                    ))}
                </Select>
            )}

            <FormField label="Notes" name="notes" type="textarea" value={values.notes} onChange={setField} error={errors.notes} />

            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                <Button variant="secondary" onClick={onCancel}>Cancel</Button>
                <Button type="submit" loading={saving}>{isEdit ? 'Update Invoice' : 'Save Invoice'}</Button>
            </div>
        </form>
    );
}
