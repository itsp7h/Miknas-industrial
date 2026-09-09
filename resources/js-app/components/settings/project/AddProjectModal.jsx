import { useState } from 'react';
import Modal from '../../ui/Modal';

/** Blade's "New Project" modal: name plus an optional company. */
export default function AddProjectModal({ open, companies, onClose, onSave }) {
    const [name, setName] = useState('');
    const [companyId, setCompanyId] = useState('');
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    async function save() {
        if (!name.trim()) {
            setError('Project name is required.');

            return;
        }
        setSaving(true);
        setError('');
        try {
            await onSave({ name: name.trim(), company_id: companyId });
            setName('');
            setCompanyId('');
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
        <Modal open={open} title="New Project" onClose={onClose}>
            <div className="mb-4">
                <label htmlFor="new-project-name" className="form-label">
                    Project Name <span className="text-red-500">*</span>
                </label>
                <input
                    id="new-project-name" type="text" className="form-input" style={{ width: '100%' }}
                    placeholder="e.g. New Warehouse" autoFocus
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') save();
                        if (e.key === 'Escape') onClose();
                    }}
                />
                {error && <p style={{ color: '#dc2626', fontSize: 12, marginTop: 4 }}>{error}</p>}
            </div>

            <div className="mb-4">
                <label htmlFor="new-project-company" className="form-label">Company</label>
                <select
                    id="new-project-company" className="form-input" style={{ width: '100%' }}
                    value={companyId}
                    onChange={(e) => setCompanyId(e.target.value)}
                >
                    <option value="">— No company —</option>
                    {companies.map((company) => (
                        <option key={company.id} value={company.id}>{company.name}</option>
                    ))}
                </select>
            </div>

            <div className="mt-6 flex items-center justify-end gap-3">
                <button type="button" onClick={onClose} className="btn-secondary">Cancel</button>
                <button type="button" onClick={save} className="btn-primary" disabled={saving}>
                    {saving ? 'Saving…' : 'Save Project'}
                </button>
            </div>
        </Modal>
    );
}
