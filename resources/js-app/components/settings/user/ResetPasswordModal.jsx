import { useState } from 'react';
import Modal from '../../ui/Modal';

const SECTION_LABEL = {
    fontSize: 11, fontWeight: 700, color: '#64748b', textTransform: 'uppercase',
    letterSpacing: '.05em', marginBottom: 10,
};

const EMAIL_HELP = 'A reset link will be emailed. Their current password keeps working until they follow it.';
const PASSWORD_HELP = 'The password below replaces the current one immediately — no email will be sent.';

/**
 * Reset an existing user's password, in the two modes the new-user form already
 * offers: email them the link, or set one now for somebody who cannot receive
 * mail. Deliberately the same shape as NewUserModal — an admin doing this has
 * almost certainly just done the other.
 *
 * The form is keyed on the user by its caller, so reopening it for someone else
 * starts blank rather than carrying the last person's typing over.
 */
export default function ResetPasswordModal({ user, onClose, onSave }) {
    const [mode, setMode] = useState('email');
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    function handleClose() {
        setMode('email');
        setPassword('');
        setConfirmation('');
        setErrors({});
        onClose();
    }

    function switchMode(next) {
        setMode(next);
        if (next === 'email') {
            setPassword('');
            setConfirmation('');
        }
        setErrors({});
    }

    async function save() {
        setSaving(true);
        setErrors({});
        const payload = { mode };
        if (mode === 'password') {
            payload.password = password;
            payload.password_confirmation = confirmation;
        }
        try {
            await onSave(user, payload);
            handleClose();
        } catch (err) {
            setErrors(Object.fromEntries(
                Object.entries(err?.errors ?? {}).map(([key, messages]) => [key, messages[0]])
            ));
            if (!err?.errors && err?.message) setErrors({ password: err.message });
        } finally {
            setSaving(false);
        }
    }

    return (
        <Modal open={Boolean(user)} title="Reset Password" onClose={handleClose}>
            <p style={{ fontSize: 13, color: '#374151', marginBottom: 16 }}>
                {user?.name}
                <span style={{ color: '#94a3b8' }}> · {user?.email}</span>
            </p>

            <div style={SECTION_LABEL}>Method</div>
            <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, color: '#374151', marginBottom: 8 }}>
                <input
                    type="radio" name="reset-password-mode" value="email"
                    checked={mode === 'email'} onChange={() => switchMode('email')}
                />
                Email reset link
            </label>
            <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, color: '#374151' }}>
                <input
                    type="radio" name="reset-password-mode" value="password"
                    checked={mode === 'password'} onChange={() => switchMode('password')}
                />
                Set password now
            </label>

            {mode === 'password' && (
                <div style={{ marginTop: 12 }}>
                    <div className="mb-4">
                        <label htmlFor="reset-password" className="form-label">New Password</label>
                        <input
                            id="reset-password" type="password" autoComplete="new-password"
                            className="form-input" style={{ width: '100%' }}
                            value={password} onChange={(e) => setPassword(e.target.value)}
                        />
                        {errors.password && <p style={{ color: '#dc2626', fontSize: 12, marginTop: 4 }}>{errors.password}</p>}
                    </div>
                    <div>
                        <label htmlFor="reset-password-confirmation" className="form-label">Confirm Password</label>
                        <input
                            id="reset-password-confirmation" type="password" autoComplete="new-password"
                            className="form-input" style={{ width: '100%' }}
                            value={confirmation} onChange={(e) => setConfirmation(e.target.value)}
                        />
                    </div>
                </div>
            )}

            {mode === 'email' && errors.password && (
                <p style={{ color: '#dc2626', fontSize: 12, marginTop: 10 }}>{errors.password}</p>
            )}

            <p style={{ fontSize: 12, color: '#94a3b8', marginTop: 14 }}>
                {mode === 'password' ? PASSWORD_HELP : EMAIL_HELP}
            </p>

            <div className="mt-6 flex items-center justify-end gap-3">
                <button type="button" onClick={handleClose} className="btn-secondary">Cancel</button>
                <button type="button" onClick={save} className="btn-primary" disabled={saving}>
                    {saving ? 'Working…' : (mode === 'password' ? 'Set Password' : 'Send Reset Link')}
                </button>
            </div>
        </Modal>
    );
}
