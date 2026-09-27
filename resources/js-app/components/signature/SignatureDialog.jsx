import { useState } from 'react';
import Modal from '../ui/Modal';
import SignatureCapture from './SignatureCapture';
import { apiPut } from '../../api/client';

/**
 * Asked for when issuing an LPO without a saved signature. What is drawn or
 * uploaded here is saved to the profile, so it is asked for once; `onSaved`
 * then carries on with whatever was being issued.
 */
export default function SignatureDialog({ open, onClose, onSaved }) {
    const [image, setImage] = useState(null);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    async function save() {
        setSaving(true);
        setError('');
        try {
            await apiPut('/profile/signature', { signature_image: image });
            await onSaved();
        } catch (err) {
            setError(err?.errors?.signature_image?.[0] || err?.message || 'Could not save your signature.');
        } finally {
            setSaving(false);
        }
    }

    if (!open) return null;

    return (
        <Modal open title="Add your signature" onClose={onClose}>
            <p style={{ fontSize: 13.5, color: '#475569', marginTop: 0, marginBottom: 14 }}>
                Every LPO carries the signature of the person who issues it. Draw or upload yours once:
                it is saved to your profile and used on every LPO you issue from now on.
            </p>
            <SignatureCapture onChange={setImage} />
            {error && <p style={{ color: '#dc2626', fontSize: 13, marginTop: 8 }}>{error}</p>}
            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 18 }}>
                <button type="button" onClick={onClose} className="btn-secondary">Cancel</button>
                <button type="button" onClick={save} disabled={!image || saving} className="btn-primary">
                    {saving ? 'Saving…' : 'Save and continue'}
                </button>
            </div>
        </Modal>
    );
}
