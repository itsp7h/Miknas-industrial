import { useState } from 'react';
import ConfirmModal from '../../ui/ConfirmModal';
import { useAccess } from '../../../layouts/AccessContext';
import useRequesters from './useRequesters';

const DISABLED = { opacity: 0.5, cursor: 'not-allowed' };
const denied = (action) => `You do not have permission to ${action} people on this list`;

/**
 * System → Requested By: the people the MPR form's Requested By offers, each
 * ticked against the companies they raise requests for.
 *
 * One tree with a `compact` flag rather than a desktop/mobile pair: the form is
 * a name and a set of checkboxes, and two copies of it would drift.
 *
 * Actions the viewer may not take stay on the page, disabled with the reason
 * (CLAUDE.md #14).
 */
export default function RequesterCard({ compact = false }) {
    const r = useRequesters();
    const { can } = useAccess();
    const [query, setQuery] = useState('');

    const canCreate = can('requesters.create');
    const canEdit = can('requesters.edit');
    const canDelete = can('requesters.delete');
    // The form either adds or edits; which permission it needs follows.
    const canSubmit = r.editing ? canEdit : canCreate;

    const q = query.trim().toLowerCase();
    const shown = q
        ? r.requesters.filter((p) => `${p.name} ${p.companies.map((c) => c.name).join(' ')}`.toLowerCase().includes(q))
        : r.requesters;

    return (
        <div>
            <div style={{
                background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
                padding: compact ? 12 : 16, marginBottom: 16,
            }}>
                <h2 style={{ fontSize: 15, fontWeight: 600, color: '#0f172a', marginBottom: 12 }}>
                    {r.editing ? `Edit ${r.editing.name}` : 'Add a person'}
                </h2>

                <div style={{ marginBottom: 12 }}>
                    <label htmlFor="rq-name" className="form-label">Name</label>
                    <input
                        id="rq-name"
                        className="form-input"
                        value={r.values.name}
                        onChange={(e) => r.setName(e.target.value)}
                        placeholder="e.g. Ali Hassan"
                        disabled={!canSubmit}
                        style={canSubmit ? undefined : DISABLED}
                    />
                    {r.errors.name && <p className="text-sm text-red-600 mt-1">{r.errors.name}</p>}
                </div>

                <fieldset style={{ border: 0, padding: 0, margin: '0 0 12px' }} disabled={!canSubmit}>
                    <legend className="form-label">Companies</legend>
                    {r.companies.length === 0 && (
                        <p style={{ fontSize: 13, color: '#64748b' }}>No active companies — add one under System → Companies.</p>
                    )}
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: compact ? 8 : 12 }}>
                        {r.companies.map((company) => (
                            <label
                                key={company.id}
                                style={{
                                    display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 14, color: '#334155',
                                    border: '1px solid #e2e8f0', borderRadius: 8, padding: '6px 10px',
                                    ...(canSubmit ? { cursor: 'pointer' } : DISABLED),
                                }}
                            >
                                <input
                                    type="checkbox"
                                    checked={r.values.company_ids.includes(company.id)}
                                    onChange={() => r.toggleCompany(company.id)}
                                />
                                {company.name}
                            </label>
                        ))}
                    </div>
                    {r.errors.company_ids && <p className="text-sm text-red-600 mt-1">{r.errors.company_ids}</p>}
                </fieldset>

                <div style={{ display: 'flex', gap: 8 }}>
                    <button
                        type="button"
                        onClick={r.save}
                        disabled={!canSubmit || r.saving}
                        title={canSubmit ? undefined : denied(r.editing ? 'edit' : 'add')}
                        className="btn-primary btn-sm"
                        style={canSubmit ? undefined : DISABLED}
                    >
                        {r.saving ? 'Saving…' : (r.editing ? 'Save' : 'Add')}
                    </button>
                    {r.editing && (
                        <button type="button" onClick={r.openNew} className="btn-secondary btn-sm">Cancel</button>
                    )}
                </div>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10 }}>
                <input
                    type="search"
                    aria-label="Search people"
                    className="form-input"
                    placeholder="Search by name or company…"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    style={{ maxWidth: compact ? undefined : 320 }}
                />
                <span style={{ fontSize: 13, color: '#64748b', whiteSpace: 'nowrap' }}>
                    {q ? `${shown.length} of ${r.requesters.length}` : r.requesters.length} {r.requesters.length === 1 ? 'person' : 'people'}
                </span>
            </div>

            {r.requesters.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>No one yet. Add the people who raise purchase requests.</p>
            )}
            {r.requesters.length > 0 && shown.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>No one matches &ldquo;{query}&rdquo;.</p>
            )}

            {shown.map((person) => (
                <div
                    key={person.id}
                    style={{
                        background: '#fff', border: '1px solid #e2e8f0', borderRadius: 10,
                        padding: 12, marginBottom: 8,
                        display: 'flex', alignItems: compact ? 'flex-start' : 'center', justifyContent: 'space-between', gap: 12,
                        flexDirection: compact ? 'column' : 'row',
                    }}
                >
                    <div style={{ minWidth: 0 }}>
                        <div style={{ fontWeight: 600, color: '#0f172a', fontSize: 14 }}>{person.name}</div>
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 6 }}>
                            {person.companies.map((company) => (
                                <span
                                    key={company.id}
                                    style={{
                                        fontSize: 11, fontWeight: 600, padding: '2px 8px', borderRadius: 20,
                                        background: '#eff6ff', color: '#1d4ed8',
                                    }}
                                >
                                    {company.name}
                                </span>
                            ))}
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 8, flexShrink: 0 }}>
                        <button
                            type="button"
                            onClick={() => r.openEdit(person)}
                            disabled={!canEdit}
                            title={canEdit ? undefined : denied('edit')}
                            className="btn-secondary btn-sm"
                            style={canEdit ? undefined : DISABLED}
                        >
                            Edit
                        </button>
                        <button
                            type="button"
                            onClick={() => r.setDeleting(person)}
                            disabled={!canDelete}
                            title={canDelete ? undefined : denied('remove')}
                            className="btn-danger btn-sm"
                            style={canDelete ? undefined : DISABLED}
                        >
                            Delete
                        </button>
                    </div>
                </div>
            ))}

            <ConfirmModal
                open={!!r.deleting}
                title="Remove this person?"
                body={r.deleting
                    ? `"${r.deleting.name}" will no longer be offered as Requested By on new purchase requests. Requests already raised keep their name.`
                    : ''}
                onConfirm={r.confirmDelete}
                onCancel={() => r.setDeleting(null)}
            />
        </div>
    );
}
