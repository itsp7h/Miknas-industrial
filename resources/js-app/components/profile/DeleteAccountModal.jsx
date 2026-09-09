import { useState } from 'react';
import Modal from '../ui/Modal';

/** Breeze's confirm-deletion dialog: one password field and no way back. */
export default function DeleteAccountModal({ open, onClose, onConfirm }) {
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [deleting, setDeleting] = useState(false);

    function close() {
        setPassword('');
        setError('');
        onClose();
    }

    async function confirm() {
        setDeleting(true);
        setError('');
        try {
            await onConfirm(password);
        } catch (err) {
            setError(err?.errors?.password?.[0] || err?.message || 'Failed to delete the account.');
            setPassword('');
        } finally {
            setDeleting(false);
        }
    }

    return (
        <Modal open={open} title="Are you sure you want to delete your account?" onClose={close}>
            <p style={{ fontSize: 14, color: '#4b5563' }}>
                Once your account is deleted, all of its resources and data will be permanently deleted.
                Please enter your password to confirm you would like to permanently delete your account.
            </p>

            <div style={{ marginTop: 24 }}>
                <label htmlFor="delete-account-password" className="form-label sr-only">Password</label>
                <input
                    id="delete-account-password" type="password" className="form-input"
                    style={{ width: '75%' }} placeholder="Password" autoComplete="current-password"
                    value={password} onChange={(e) => setPassword(e.target.value)}
                />
                {error && <p style={{ color: '#dc2626', fontSize: 12, marginTop: 8 }}>{error}</p>}
            </div>

            <div className="mt-6 flex items-center justify-end gap-3">
                <button type="button" onClick={close} className="btn-secondary">Cancel</button>
                <button type="button" onClick={confirm} className="btn-danger" disabled={deleting || !password}>
                    {deleting ? 'Deleting…' : 'Delete Account'}
                </button>
            </div>
        </Modal>
    );
}
