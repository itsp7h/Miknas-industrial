import { useState } from 'react';
import { FolderIcon, PinIcon } from './icons';

const INACTIVE_BADGE = {
    display: 'inline-block', padding: '1px 7px', borderRadius: 9, fontSize: 11,
    fontWeight: 600, background: '#fee2e2', color: '#dc2626',
};

const COMPANY_PILL = {
    fontSize: 12, color: '#3b82f6', opacity: 0.8, background: '#dbeafe',
    padding: '1px 8px', borderRadius: 9,
};

function firstError(err) {
    const errors = err?.errors;
    if (errors) {
        const key = Object.keys(errors)[0];
        if (key) return errors[key][0];
    }

    return err?.message || 'Something went wrong.';
}

const coord = (value) => Number(value).toFixed(6);

/** Blade's inline edit strip: name, company, Active. */
function EditStrip({ project, companies, onSave, onCancel }) {
    const [name, setName] = useState(project.name);
    const [companyId, setCompanyId] = useState(project.company_id ?? '');
    const [isActive, setIsActive] = useState(project.is_active);
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    async function save() {
        if (!name.trim()) {
            setError('Name is required.');

            return;
        }
        setSaving(true);
        setError('');
        try {
            await onSave({ name: name.trim(), company_id: companyId, is_active: isActive });
        } catch (err) {
            setError(firstError(err));
        } finally {
            setSaving(false);
        }
    }

    return (
        <div style={{
            display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap',
            padding: '0.65rem 1.25rem', background: '#f0f9ff', borderBottom: '1px solid #bae6fd',
        }}>
            <input
                type="text" className="form-input" autoFocus aria-label="Project name"
                style={{ flex: 1, fontSize: 13, minWidth: 140 }}
                value={name}
                onChange={(e) => setName(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') save();
                    if (e.key === 'Escape') onCancel();
                }}
            />
            <select
                className="form-input" aria-label="Company"
                style={{ fontSize: 13, width: 'auto', minWidth: 140 }}
                value={companyId ?? ''}
                onChange={(e) => setCompanyId(e.target.value)}
            >
                <option value="">— No company —</option>
                {companies.map((company) => (
                    <option key={company.id} value={company.id}>{company.name}</option>
                ))}
            </select>
            <label style={{ display: 'flex', alignItems: 'center', gap: 4, fontSize: 12, color: '#374151', whiteSpace: 'nowrap', cursor: 'pointer' }}>
                <input type="checkbox" style={{ width: 14, height: 14 }} checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />
                Active
            </label>
            <button type="button" onClick={save} className="btn-primary" disabled={saving} style={{ padding: '5px 14px', fontSize: 12, whiteSpace: 'nowrap' }}>
                {saving ? 'Saving…' : 'Save'}
            </button>
            <button type="button" onClick={onCancel} className="btn-secondary btn-sm">Cancel</button>
            {error && <p style={{ color: '#dc2626', fontSize: 12, margin: 0, width: '100%' }}>{error}</p>}
        </div>
    );
}

/**
 * One project: a blue gradient header with its name, company pill and location
 * count, an inline edit strip, then its locations — each showing its address and
 * its coordinates to six decimals, as Blade did.
 */
export default function ProjectCard({ project, companies, onSave, onDelete, onAddLocation, onEditLocation, onDeleteLocation }) {
    const [editing, setEditing] = useState(false);
    const locations = project.locations ?? [];

    return (
        <div style={{ border: '1px solid #e2e8f0', borderRadius: 14, overflow: 'hidden', marginBottom: 16, background: '#fff' }}>
            <div style={{
                display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10, flexWrap: 'wrap',
                padding: '0.875rem 1.25rem', background: 'linear-gradient(135deg,#eff6ff,#dbeafe)',
            }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, minWidth: 0, flexWrap: 'wrap' }}>
                    <FolderIcon colour={project.is_active ? '#2563eb' : '#9ca3af'} />
                    <span style={{ fontSize: 15, fontWeight: 700, color: '#1e40af' }}>{project.name}</span>
                    {!project.is_active && <span style={INACTIVE_BADGE}>Inactive</span>}
                    {project.company_name && <span style={COMPANY_PILL}>{project.company_name}</span>}
                    <span style={{ fontSize: 12, color: '#6366f1', opacity: 0.75 }}>
                        {locations.length} {locations.length === 1 ? 'location' : 'locations'}
                    </span>
                </div>
                <div style={{ display: 'flex', gap: 6, alignItems: 'center', flexShrink: 0 }}>
                    <button
                        type="button" onClick={() => onAddLocation(project)}
                        className="btn-secondary btn-sm" style={{ borderColor: '#93c5fd', color: '#1d4ed8' }}
                    >
                        + Location
                    </button>
                    <button type="button" onClick={() => setEditing(true)} className="btn-secondary btn-sm">Edit</button>
                    <button type="button" onClick={() => onDelete(project)} className="btn-danger btn-sm">Delete</button>
                </div>
            </div>

            {editing && (
                <EditStrip
                    project={project} companies={companies}
                    onSave={async (values) => { await onSave(project, values); setEditing(false); }}
                    onCancel={() => setEditing(false)}
                />
            )}

            {locations.length === 0 && (
                <div style={{ padding: '14px 1.25rem', color: '#9ca3af', fontSize: 13 }}>
                    No locations yet — click &quot;+ Location&quot; to add one.
                </div>
            )}

            {locations.map((location) => (
                <div
                    key={location.id}
                    style={{
                        display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 8,
                        padding: '0.65rem 1rem 0.65rem 1.25rem', borderBottom: '1px solid #f1f5f9',
                    }}
                >
                    <div style={{ display: 'flex', alignItems: 'flex-start', gap: 8, flex: 1, minWidth: 0 }}>
                        <PinIcon colour={location.is_active ? '#22c55e' : '#9ca3af'} style={{ marginTop: 3 }} />
                        <div style={{ flex: 1, minWidth: 0 }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
                                <span style={{ fontSize: 13, fontWeight: 600, color: '#1e293b' }}>{location.name}</span>
                                {!location.is_active && <span style={INACTIVE_BADGE}>Inactive</span>}
                            </div>
                            {location.address && (
                                <div style={{ fontSize: 12, color: '#64748b', marginTop: 2 }}>{location.address}</div>
                            )}
                            {location.latitude != null && location.longitude != null && (
                                <div className="font-mono" style={{ fontSize: 11, color: '#94a3b8', marginTop: 1 }}>
                                    {coord(location.latitude)}°, {coord(location.longitude)}°
                                </div>
                            )}
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 4, flexShrink: 0, marginTop: 1 }}>
                        <button
                            type="button" onClick={() => onEditLocation(project, location)}
                            className="btn-secondary btn-sm" style={{ padding: '3px 8px', fontSize: 12 }}
                        >
                            Edit
                        </button>
                        <button
                            type="button" onClick={() => onDeleteLocation(project, location)}
                            className="btn-danger btn-sm" style={{ padding: '3px 8px', fontSize: 12 }}
                        >
                            Delete
                        </button>
                    </div>
                </div>
            ))}
        </div>
    );
}
