import { useState } from 'react';
import { apiPost, apiPut } from '../../../api/client';

const EMPTY = {
    name: '', contact_person: '', email: '', phone: '', whatsapp_number: '',
    address: '', tax_number: '', credit_limit: '0', is_active: true,
};

/**
 * Blade's two-up form, restyled onto the shared `.form-label` / `.form-input`
 * classes it used: name and address span the full width, the rest pair up.
 */
export default function CustomerForm({ customer, onSaved, onCancel }) {
    const [values, setValues] = useState(() => ({
        ...EMPTY,
        ...(customer ?? {}),
        // A null column would put React's inputs into uncontrolled mode.
        ...Object.fromEntries(
            ['contact_person', 'email', 'phone', 'whatsapp_number', 'address', 'tax_number', 'credit_limit']
                .map((key) => [key, customer?.[key] ?? EMPTY[key]])
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

    const messages = Object.values(errors);

    return (
        <form onSubmit={handleSubmit}>
            {messages.length > 0 && (
                <div className="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                    <ul className="list-disc list-inside space-y-1">
                        {messages.map((message) => <li key={message}>{message}</li>)}
                    </ul>
                </div>
            )}

            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div className="sm:col-span-2">
                    <label htmlFor="name" className="form-label">
                        Customer Name <span className="text-red-500">*</span>
                    </label>
                    <input
                        id="name" className="form-input" type="text" required
                        value={values.name} onChange={(e) => setField('name', e.target.value)}
                    />
                </div>

                <div>
                    <label htmlFor="contact_person" className="form-label">Contact Person</label>
                    <input
                        id="contact_person" className="form-input" type="text"
                        value={values.contact_person} onChange={(e) => setField('contact_person', e.target.value)}
                    />
                </div>

                <div>
                    <label htmlFor="email" className="form-label">Email</label>
                    <input
                        id="email" className="form-input" type="email"
                        value={values.email} onChange={(e) => setField('email', e.target.value)}
                    />
                </div>

                <div>
                    <label htmlFor="phone" className="form-label">Phone</label>
                    <input
                        id="phone" className="form-input" type="text"
                        value={values.phone} onChange={(e) => setField('phone', e.target.value)}
                    />
                </div>

                <div>
                    <label htmlFor="whatsapp_number" className="form-label">WhatsApp Number</label>
                    <input
                        id="whatsapp_number" className="form-input" type="text" placeholder="+971501234567"
                        value={values.whatsapp_number} onChange={(e) => setField('whatsapp_number', e.target.value)}
                    />
                    <p style={{ fontSize: 12, color: '#6b7280', marginTop: 4 }}>
                        International format. Used for WhatsApp notifications.
                    </p>
                </div>

                <div>
                    <label htmlFor="tax_number" className="form-label">Tax Number</label>
                    <input
                        id="tax_number" className="form-input" type="text"
                        value={values.tax_number} onChange={(e) => setField('tax_number', e.target.value)}
                    />
                </div>

                <div>
                    <label htmlFor="credit_limit" className="form-label">Credit Limit</label>
                    <input
                        id="credit_limit" className="form-input" type="number" min="0" step="0.01"
                        value={values.credit_limit} onChange={(e) => setField('credit_limit', e.target.value)}
                    />
                </div>

                <div className="sm:col-span-2">
                    <label htmlFor="address" className="form-label">Address</label>
                    <textarea
                        id="address" className="form-textarea" rows={3}
                        value={values.address} onChange={(e) => setField('address', e.target.value)}
                    />
                </div>

                <div className="sm:col-span-2 flex items-center gap-2">
                    <input
                        type="checkbox" id="is_active"
                        className="h-4 w-4 text-blue-600 border-gray-300 rounded"
                        checked={!!values.is_active}
                        onChange={(e) => setField('is_active', e.target.checked)}
                    />
                    <label htmlFor="is_active" className="form-label mb-0">Active</label>
                </div>
            </div>

            <div className="mt-6 flex items-center gap-3">
                <button type="submit" className="btn-primary" disabled={saving}>
                    {saving ? 'Saving…' : (customer ? 'Update Customer' : 'Save Customer')}
                </button>
                <button type="button" onClick={onCancel} className="btn-secondary">Cancel</button>
            </div>
        </form>
    );
}
