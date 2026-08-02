import { useState } from 'react';
import FormField from '../../ui/FormField';
import { apiPost, apiPut } from '../../../api/client';

const BLANK = { name: '', supplier_code: '', category: '', contact_person: '', email: '', phone: '', whatsapp_number: '', address: '', credit_days: '' };

export default function SupplierForm({ supplier, onSaved, onCancel }) {
    const [values, setValues] = useState({ ...BLANK, ...supplier });
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    function handleChange(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
    }

    async function handleSave() {
        if (!values.name.trim()) {
            setErrors({ name: 'Name is required.' });
            return;
        }
        setErrors({});
        setSaving(true);
        try {
            const response = supplier
                ? await apiPut(`/purchase/suppliers/${supplier.id}`, values)
                : await apiPost('/purchase/suppliers', values);
            onSaved(response.data);
        } catch (err) {
            setErrors(err.errors ? Object.fromEntries(Object.entries(err.errors).map(([k, v]) => [k, v[0]])) : {});
        } finally {
            setSaving(false);
        }
    }

    return (
        <div>
            <FormField label="Name" name="name" value={values.name} onChange={handleChange} error={errors.name} />
            <FormField label="Supplier Code" name="supplier_code" value={values.supplier_code} onChange={handleChange} />
            <FormField label="Category" name="category" value={values.category} onChange={handleChange} />
            <FormField label="Contact Person" name="contact_person" value={values.contact_person} onChange={handleChange} />
            <FormField label="Email" name="email" value={values.email} onChange={handleChange} type="email" />
            <FormField label="Phone" name="phone" value={values.phone} onChange={handleChange} />
            <FormField label="WhatsApp Number" name="whatsapp_number" value={values.whatsapp_number} onChange={handleChange} />
            <FormField label="Address" name="address" value={values.address} onChange={handleChange} type="textarea" />
            <FormField label="Credit Days" name="credit_days" value={values.credit_days} onChange={handleChange} type="number" />
            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end', marginTop: 16 }}>
                <button onClick={onCancel} disabled={saving}>Cancel</button>
                <button onClick={handleSave} disabled={saving}>Save</button>
            </div>
        </div>
    );
}
