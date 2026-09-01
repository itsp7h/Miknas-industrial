import { useState } from 'react';
import { BuildingIcon, PeopleIcon } from './icons';

const INACTIVE_BADGE = {
    display: 'inline-block', padding: '1px 7px', borderRadius: 9, fontSize: 11,
    fontWeight: 600, background: '#fee2e2', color: '#dc2626',
};

const EDIT_STRIP = {
    display: 'flex', alignItems: 'center', gap: 8, padding: '0.65rem 1.25rem',
    background: '#f0f9ff', borderBottom: '1px solid #bae6fd', flexWrap: 'wrap',
};

function firstError(err) {
    const errors = err?.errors;
    if (errors) {
        const key = Object.keys(errors)[0];
        if (key) return errors[key][0];
    }

    return err?.message || 'Something went wrong.';
}

/** Inline name + Active editor, used for both the company and its departments. */
function InlineEditor({ value, active, onSave, onCancel, placeholder, compact = false }) {
    const [name, setName] = useState(value ?? '');
    const [isActive, setIsActive] = useState(active ?? true);
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
            await onSave({ name: name.trim(), is_active: isActive });
        } catch (err) {
            setError(firstError(err));
        } finally {
            setSaving(false);
        }
    }

    return (
        <div style={EDIT_STRIP}>
            <input
                type="text" className="form-input" autoFocus
                aria-label={placeholder ?? 'Name'}
                placeholder={placeholder}
                style={{ flex: 1, fontSize: compact ? 12 : 13, minWidth: 140 }}
                value={name}
                onChange={(e) => setName(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') save();
                    if (e.key === 'Escape') onCancel();
                }}
            />
            {active !== undefined && (
                <label style={{ display: 'flex', alignItems: 'center', gap: 4, fontSize: 12, color: '#374151', whiteSpace: 'nowrap', cursor: 'pointer' }}>
                    <input
                        type="checkbox" style={{ width: 14, height: 14 }}
                        checked={isActive}
                        onChange={(e) => setIsActive(e.target.checked)}
                    />
                    Active
                </label>
            )}
            <button type="button" onClick={save} className="btn-primary" disabled={saving} style={{ padding: '5px 14px', fontSize: 12, whiteSpace: 'nowrap' }}>
                {saving ? 'Saving…' : 'Save'}
            </button>
            <button type="button" onClick={onCancel} className="btn-secondary btn-sm">Cancel</button>
            {error && <p style={{ color: '#dc2626', fontSize: 12, margin: 0, width: '100%' }}>{error}</p>}
        </div>
    );
}

/**
 * One company: an indigo gradient header carrying its name, department count and
 * actions, an inline edit strip, then its departments as rows with their own
 * inline add and edit rows. This is the Blade page's structure, with the
 * show/hide of each strip held in React state instead of toggled CSS classes.
 */
export default function CompanyCard({ company, onSave, onDelete, onAddDepartment, onSaveDepartment, onDeleteDepartment }) {
    const [editing, setEditing] = useState(false);
    const [addingDepartment, setAddingDepartment] = useState(false);
    const [editingDepartmentId, setEditingDepartmentId] = useState(null);

    const departments = company.departments ?? [];

    return (
        <div style={{ border: '1px solid #e2e8f0', borderRadius: 14, overflow: 'hidden', marginBottom: 16, background: '#fff' }}>
            <div style={{
                display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10,
                padding: '0.875rem 1.25rem', background: 'linear-gradient(135deg,#eef2ff,#e0e7ff)', flexWrap: 'wrap',
            }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, minWidth: 0 }}>
                    <BuildingIcon />
                    <span style={{ fontSize: 15, fontWeight: 700, color: '#3730a3' }}>{company.name}</span>
                    {!company.is_active && <span style={INACTIVE_BADGE}>Inactive</span>}
                    <span style={{ fontSize: 12, color: '#7c3aed', opacity: 0.8 }}>
                        {departments.length} {departments.length === 1 ? 'dept' : 'depts'}
                    </span>
                </div>
                <div style={{ display: 'flex', gap: 6, alignItems: 'center', flexShrink: 0 }}>
                    <button
                        type="button" onClick={() => setAddingDepartment(true)}
                        className="btn-secondary btn-sm" style={{ borderColor: '#a78bfa', color: '#6d28d9' }}
                    >
                        + Department
                    </button>
                    <button type="button" onClick={() => setEditing(true)} className="btn-secondary btn-sm">Edit</button>
                    <button type="button" onClick={() => onDelete(company)} className="btn-danger btn-sm">Delete</button>
                </div>
            </div>

            {editing && (
                <InlineEditor
                    value={company.name} active={company.is_active} placeholder="Company name"
                    onSave={async (values) => { await onSave(company, values); setEditing(false); }}
                    onCancel={() => setEditing(false)}
                />
            )}

            {addingDepartment && (
                <InlineEditor
                    value="" placeholder="Department name…" compact
                    onSave={async ({ name }) => { await onAddDepartment(company, name); setAddingDepartment(false); }}
                    onCancel={() => setAddingDepartment(false)}
                />
            )}

            {departments.length === 0 && (
                <div style={{ padding: '14px 1.25rem', color: '#9ca3af', fontSize: 13 }}>
                    No departments yet — click &quot;+ Department&quot; to add one.
                </div>
            )}

            {departments.map((department) => (
                <div key={department.id}>
                    <div style={{
                        display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                        padding: '0.6rem 1rem 0.6rem 1.25rem', borderBottom: '1px solid #f1f5f9', gap: 8,
                    }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flex: 1, minWidth: 0 }}>
                            <PeopleIcon size={13} colour={department.is_active ? '#7c3aed' : '#9ca3af'} />
                            <span style={{ fontSize: 13, fontWeight: 500, color: '#1e293b' }}>{department.name}</span>
                            {!department.is_active && <span style={INACTIVE_BADGE}>Inactive</span>}
                        </div>
                        <div style={{ display: 'flex', gap: 4, flexShrink: 0 }}>
                            <button
                                type="button" onClick={() => setEditingDepartmentId(department.id)}
                                className="btn-secondary btn-sm" style={{ padding: '3px 8px', fontSize: 12 }}
                            >
                                Edit
                            </button>
                            <button
                                type="button" onClick={() => onDeleteDepartment(company, department)}
                                className="btn-danger btn-sm" style={{ padding: '3px 8px', fontSize: 12 }}
                            >
                                Delete
                            </button>
                        </div>
                    </div>

                    {editingDepartmentId === department.id && (
                        <InlineEditor
                            value={department.name} active={department.is_active} placeholder="Department name" compact
                            onSave={async (values) => { await onSaveDepartment(company, department, values); setEditingDepartmentId(null); }}
                            onCancel={() => setEditingDepartmentId(null)}
                        />
                    )}
                </div>
            ))}
        </div>
    );
}
