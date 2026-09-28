import { useState } from 'react';
import ConfirmModal from '../../ui/ConfirmModal';
import { useAccess } from '../../../layouts/AccessContext';
import RequesterModal from './RequesterModal';
import useRequesters from './useRequesters';

const DISABLED = { opacity: 0.5, cursor: 'not-allowed' };
const denied = (action) => `You do not have permission to ${action} people on this list`;

/**
 * System → Requested By: the people the MPR form's Requested By offers, each
 * ticked against the companies they raise requests for.
 *
 * One tree with a `compact` flag rather than a desktop/mobile pair: the form is
 * a name and a set of checkboxes, and two copies of it would drift. Adding and
 * editing happen in a dialog (`RequesterModal`), so the page is the list.
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

    const q = query.trim().toLowerCase();
    const shown = q
        ? r.requesters.filter((p) => [p.name, ...p.companies.map((c) => c.name), ...(p.phones ?? [])]
            .join(' ').toLowerCase().includes(q))
        : r.requesters;

    return (
        <div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10, flexWrap: compact ? 'wrap' : 'nowrap' }}>
                <input
                    type="search"
                    aria-label="Search people"
                    className="form-input"
                    placeholder="Search by name, company or number…"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    style={{ maxWidth: compact ? undefined : 320 }}
                />
                <span style={{ fontSize: 13, color: '#64748b', whiteSpace: 'nowrap' }}>
                    {q ? `${shown.length} of ${r.requesters.length}` : r.requesters.length} {r.requesters.length === 1 ? 'person' : 'people'}
                </span>
                <button
                    type="button"
                    onClick={r.openNew}
                    disabled={!canCreate}
                    title={canCreate ? undefined : denied('add')}
                    className="btn-primary"
                    style={{
                        marginLeft: 'auto', flexShrink: 0, whiteSpace: 'nowrap',
                        ...(compact ? { width: '100%', justifyContent: 'center' } : {}),
                        ...(canCreate ? {} : DISABLED),
                    }}
                >
                    + Add person
                </button>
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
                        {person.phones?.length > 0 && (
                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '4px 12px', marginTop: 4 }}>
                                {person.phones.map((phone) => (
                                    // tel: so a tap on a phone dials it.
                                    <a
                                        key={phone}
                                        href={`tel:${phone.replace(/[^\d+]/g, '')}`}
                                        style={{ fontSize: 13, color: '#475569', textDecoration: 'none' }}
                                    >
                                        📞 {phone}
                                    </a>
                                ))}
                            </div>
                        )}
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

            <RequesterModal r={r} compact={compact} />

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
