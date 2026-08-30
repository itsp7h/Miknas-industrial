import { useState } from 'react';
import FormField from '../../ui/FormField';
import Button from '../../ui/Button';
import { apiPost, apiPut } from '../../../api/client';

const EMPTY = {
    name: '', contact_person: '', email: '', phone: '', whatsapp_number: '',
    address: '', tax_number: '', credit_limit: '', is_active: true,
};

export default function CustomerForm({ customer, onSaved, onCancel }) {
    const [values, setValues] = useState(() => ({
        ...EMPTY,
        ...(customer ?? {}),
        ...Object.fromEntries(
            ['contact_person', 'email', 'phone', 'whatsapp_number', 'address', 'tax_number', 'credit_limit']
                .map((key) => [key, customer?.[key] ?? ''])
        ),
    }));
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    function setField(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
    }

    async function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const response = customer
                ? await apiPut(`/sales/customers/${customer.id}`, values)
                : await apiPost('/sales/customers', values);
            onSaved(response.data);
        } catch (err) {
            setErrors(Object.fromEntries(
                Object.entries(err.errors ?? {}).map(([key, messages]) => [key, messages[0]])
            ));
        } finally {
            setSaving(false);
        }
    }

    return (
        <form onSubmit={handleSubmit}>
            <FormField label="Customer Name" name="name" value={values.name} onChange={setField} error={errors.name} />
            <FormField label="Contact Person" name="contact_person" value={values.contact_person} onChange={setField} error={errors.contact_person} />
            <FormField label="Email" name="email" type="email" value={values.email} onChange={setField} error={errors.email} />
            <FormField label="Phone" name="phone" value={values.phone} onChange={setField} error={errors.phone} />
            <FormField label="WhatsApp Number" name="whatsapp_number" value={values.whatsapp_number} onChange={setField} error={errors.whatsapp_number} />
            <FormField label="Tax Number" name="tax_number" value={values.tax_number} onChange={setField} error={errors.tax_number} />
            <FormField label="Credit Limit" name="credit_limit" type="number" value={values.credit_limit} onChange={setField} error={errors.credit_limit} />
            <FormField label="Address" name="address" type="textarea" value={values.address} onChange={setField} error={errors.address} />

            <div className="mb-4">
                <label className="flex items-center gap-2 text-sm font-medium text-gray-700">
                    <input type="checkbox" checked={!!values.is_active} onChange={(e) => setField('is_active', e.target.checked)} />
                    Active
                </label>
            </div>

            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                <Button variant="secondary" onClick={onCancel}>Cancel</Button>
                <Button type="submit" loading={saving}>{customer ? 'Save Changes' : 'Create Customer'}</Button>
            </div>
        </form>
    );
}
