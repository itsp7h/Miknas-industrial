import { useState } from 'react';

const EMPTY = { current_password: '', password: '', password_confirmation: '' };

/**
 * Breeze's three password fields. Nothing is prefilled and everything is cleared
 * on success, so a password never sits in the DOM longer than the submit takes.
 */
export default function PasswordForm({ onSave }) {
    const [values, setValues] = useState(EMPTY);
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    function setField(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
    }

    async function submit(e) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            await onSave(values);
            setValues(EMPTY);
        } catch (err) {
            setErrors(Object.fromEntries(
                Object.entries(err?.errors ?? {}).map(([key, messages]) => [key, messages[0]])
            ));
        } finally {
            setSaving(false);
        }
    }

    const fields = [
        { name: 'current_password', label: 'Current Password', autoComplete: 'current-password' },
        { name: 'password', label: 'New Password', autoComplete: 'new-password' },
        { name: 'password_confirmation', label: 'Confirm Password', autoComplete: 'new-password' },
    ];

    return (
        <form onSubmit={submit}>
            {fields.map(({ name, label, autoComplete }) => (
                <div key={name} style={{ marginBottom: 20 }}>
                    <label htmlFor={`profile-${name}`} className="form-label">{label}</label>
                    <input
                        id={`profile-${name}`} type="password" className="form-input" style={{ width: '100%' }}
                        autoComplete={autoComplete}
                        value={values[name]} onChange={(e) => setField(name, e.target.value)}
                    />
                    {errors[name] && <p style={{ color: '#dc2626', fontSize: 12, marginTop: 6 }}>{errors[name]}</p>}
                </div>
            ))}

            <button type="submit" className="btn-primary" disabled={saving}>
                {saving ? 'Saving…' : 'Save'}
            </button>
        </form>
    );
}
