import { useState } from 'react';
import Modal from '../../ui/Modal';

const SECTION_LABEL = {
    fontSize: 11, fontWeight: 700, color: '#64748b', textTransform: 'uppercase',
    letterSpacing: '.05em', marginBottom: 10,
};

const EMAIL_HELP = 'The new user will receive an email with a link to set their own password.';
const PASSWORD_HELP = 'The password below will be set immediately — no email will be sent.';

/**
 * Blade's new-user modal. The two password modes matter: "Email setup link"
 * sends a reset link and never puts a password in this form, while "Set password
 * now" reveals the fields and skips the email. The server rejects a password
 * sent in email mode outright rather than ignoring it.
 */
export default function NewUserModal({ open, roles, onClose, onSave }) {
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [selectedRoles, setSelectedRoles] = useState([]);
    const [mode, setMode] = useState('email');
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    function reset() {
        setName('');
        setEmail('');
        setSelectedRoles([]);
        setMode('email');
        setPassword('');
        setConfirmation('');
        setErrors({});
    }

    function handleClose() {
        reset();
        onClose();
    }

    function switchMode(next) {
        setMode(next);
        if (next === 'email') {
            setPassword('');
            setConfirmation('');
        }
    }

    async function save() {
        setSaving(true);
        setErrors({});
        const payload = { name, email, roles: selectedRoles, mode };
        if (mode === 'password') {
            payload.password = password;
            payload.password_confirmation = confirmation;
        }
        try {
            await onSave(payload);
            reset();
            onClose();
        } catch (err) {
            setErrors(Object.fromEntries(
                Object.entries(err?.errors ?? {}).map(([key, messages]) => [key, messages[0]])
            ));
            if (!err?.errors && err?.message) setErrors({ name: err.message });
        } finally {
            setSaving(false);
        }
    }

    return (
        <Modal open={open} title="New User" onClose={handleClose}>
            <div className="mb-4">
                <label htmlFor="new-user-name" className="form-label">Name</label>
                <input
                    id="new-user-name" type="text" className="form-input" style={{ width: '100%' }}
                    value={name} onChange={(e) => setName(e.target.value)}
                />
                {errors.name && <p style={{ color: '#dc2626', fontSize: 12, marginTop: 4 }}>{errors.name}</p>}
            </div>

            <div className="mb-4">
                <label htmlFor="new-user-email" className="form-label">Email</label>
                <input
                    id="new-user-email" type="email" className="form-input" style={{ width: '100%' }}
                    value={email} onChange={(e) => setEmail(e.target.value)}
                />
                {errors.email && <p style={{ color: '#dc2626', fontSize: 12, marginTop: 4 }}>{errors.email}</p>}
            </div>

            <div style={SECTION_LABEL}>Profiles</div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8, marginBottom: 18 }}>
                {roles.map((role) => (
                    <label key={role} style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, color: '#374151' }}>
                        <input
                            type="checkbox" value={role}
                            checked={selectedRoles.includes(role)}
                            onChange={(e) => setSelectedRoles(
                                e.target.checked
                                    ? [...selectedRoles, role]
                                    : selectedRoles.filter((item) => item !== role)
                            )}
                        />
                        {role}
                    </label>
                ))}
            </div>

            <div style={SECTION_LABEL}>Password</div>
            <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, color: '#374151', marginBottom: 8 }}>
                <input
                    type="radio" name="new-user-password-mode" value="email"
                    checked={mode === 'email'} onChange={() => switchMode('email')}
                />
                Email setup link
            </label>
            <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, color: '#374151' }}>
                <input
                    type="radio" name="new-user-password-mode" value="password"
                    checked={mode === 'password'} onChange={() => switchMode('password')}
                />
                Set password now
            </label>

            {mode === 'password' && (
                <div style={{ marginTop: 12 }}>
                    <div className="mb-4">
                        <label htmlFor="new-user-password" className="form-label">Password</label>
                        <input
                            id="new-user-password" type="password" autoComplete="new-password"
                            className="form-input" style={{ width: '100%' }}
                            value={password} onChange={(e) => setPassword(e.target.value)}
                        />
                        {errors.password && <p style={{ color: '#dc2626', fontSize: 12, marginTop: 4 }}>{errors.password}</p>}
                    </div>
                    <div>
                        <label htmlFor="new-user-password-confirmation" className="form-label">Confirm Password</label>
                        <input
                            id="new-user-password-confirmation" type="password" autoComplete="new-password"
                            className="form-input" style={{ width: '100%' }}
                            value={confirmation} onChange={(e) => setConfirmation(e.target.value)}
                        />
                    </div>
                </div>
            )}

            <p style={{ fontSize: 12, color: '#94a3b8', marginTop: 14 }}>
                {mode === 'password' ? PASSWORD_HELP : EMAIL_HELP}
            </p>

            <div className="mt-6 flex items-center justify-end gap-3">
                <button type="button" onClick={handleClose} className="btn-secondary">Cancel</button>
                <button type="button" onClick={save} className="btn-primary" disabled={saving}>
                    {saving ? 'Creating…' : 'Create User'}
                </button>
            </div>
        </Modal>
    );
}
