import { useState } from 'react';
import Modal from '../../ui/Modal';

/** Blade's "New Company" modal: one field, Enter to save, Escape to close. */
export default function AddCompanyModal({ open, onClose, onSave }) {
    const [name, setName] = useState('');
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    async function save() {
        if (!name.trim()) {
            setError('Company name is required.');

            return;
        }
        setSaving(true);
        setError('');
        try {
            await onSave(name.trim());
            setName('');
            onClose();
        } catch (err) {
            const errors = err?.errors;
            const key = errors ? Object.keys(errors)[0] : null;
            setError(key ? errors[key][0] : (err?.message || 'Something went wrong.'));
        } finally {
            setSaving(false);
        }
    }

    return (
        <Modal open={open} title="New Company" onClose={onClose}>
            <label htmlFor="new-company-name" className="form-label">
                Company Name <span className="text-red-500">*</span>
            </label>
            <input
                id="new-company-name" type="text" className="form-input" style={{ width: '100%' }}
                placeholder="e.g. Miknas Industrial" autoFocus
                value={name}
                onChange={(e) => setName(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') save();
                    if (e.key === 'Escape') onClose();
                }}
            />
            {error && <p style={{ color: '#dc2626', fontSize: 12, marginTop: 4 }}>{error}</p>}

            <div className="mt-6 flex items-center justify-end gap-3">
                <button type="button" onClick={onClose} className="btn-secondary">Cancel</button>
                <button type="button" onClick={save} className="btn-primary" disabled={saving}>
                    {saving ? 'Saving…' : 'Save Company'}
                </button>
            </div>
        </Modal>
    );
}
