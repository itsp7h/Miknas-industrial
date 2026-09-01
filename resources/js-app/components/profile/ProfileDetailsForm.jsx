import { useEffect, useState } from 'react';

/** Name and email, plus Breeze's unverified-address notice. */
export default function ProfileDetailsForm({ user, onSave, onResendVerification }) {
    const [name, setName] = useState(user?.name ?? '');
    const [email, setEmail] = useState(user?.email ?? '');
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        setName(user?.name ?? '');
        setEmail(user?.email ?? '');
    }, [user]);

    async function submit(e) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            await onSave({ name, email });
        } catch (err) {
            setErrors(Object.fromEntries(
                Object.entries(err?.errors ?? {}).map(([key, messages]) => [key, messages[0]])
            ));
        } finally {
            setSaving(false);
        }
    }

    return (
        <form onSubmit={submit}>
            <div style={{ marginBottom: 20 }}>
                <label htmlFor="profile-name" className="form-label">Name</label>
                <input
                    id="profile-name" type="text" className="form-input" style={{ width: '100%' }}
                    autoComplete="name" required
                    value={name} onChange={(e) => setName(e.target.value)}
                />
                {errors.name && <p style={{ color: '#dc2626', fontSize: 12, marginTop: 6 }}>{errors.name}</p>}
            </div>

            <div style={{ marginBottom: 20 }}>
                <label htmlFor="profile-email" className="form-label">Email</label>
                <input
                    id="profile-email" type="email" className="form-input" style={{ width: '100%' }}
                    autoComplete="username" required
                    value={email} onChange={(e) => setEmail(e.target.value)}
                />
                {errors.email && <p style={{ color: '#dc2626', fontSize: 12, marginTop: 6 }}>{errors.email}</p>}

                {user && !user.email_verified && (
                    <p style={{ fontSize: 14, marginTop: 8, color: '#1f2937' }}>
                        Your email address is unverified.{' '}
                        <button
                            type="button" onClick={onResendVerification}
                            style={{ textDecoration: 'underline', fontSize: 14, color: '#4b5563', background: 'none', border: 'none', cursor: 'pointer', padding: 0 }}
                        >
                            Click here to re-send the verification email.
                        </button>
                    </p>
                )}
            </div>

            <button type="submit" className="btn-primary" disabled={saving}>
                {saving ? 'Saving…' : 'Save'}
            </button>
        </form>
    );
}
