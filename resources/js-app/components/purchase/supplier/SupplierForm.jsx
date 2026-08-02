import { useState } from 'react';
import FormField from '../../ui/FormField';
import { apiPost, apiPut } from '../../../api/client';

const BLANK = {
    name: '',
    supplier_code: '',
    category: '',
    contact_person: '',
    email: '',
    phone: '',
    whatsapp_number: '',
    address: '',
    tax_number: '',
    credit_days: '',
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

export default function SupplierForm({ supplier, onSaved, onCancel }) {
    const [values, setValues] = useState(() => normalize(supplier));
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
            <FormField label="Tax Number" name="tax_number" value={values.tax_number} onChange={handleChange} />
            <FormField label="Credit Days" name="credit_days" value={values.credit_days} onChange={handleChange} type="number" />
            <div className="mb-4">
                <label htmlFor="is_active" style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 14, fontWeight: 500 }}>
                    <input
                        id="is_active"
                        name="is_active"
                        type="checkbox"
                        checked={!!values.is_active}
                        onChange={(e) => handleChange('is_active', e.target.checked)}
                    />
                    Active
                </label>
            </div>
            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end', marginTop: 16 }}>
                <button onClick={onCancel} disabled={saving}>Cancel</button>
                <button onClick={handleSave} disabled={saving}>Save</button>
            </div>
        </div>
    );
}
