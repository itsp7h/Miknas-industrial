import { useEffect, useMemo, useState } from 'react';
import useViewport from '../../../hooks/useViewport';
import ItemRows, { blankRow } from './ItemRows';
import ProjectPicker from './ProjectPicker';
import UrgencyPicker from './UrgencyPicker';

const SECTION = {
    background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '0.875rem',
    padding: '1.25rem', marginBottom: '1.25rem',
};

function SectionTitle({ accent, children }) {
    return (
        <h3 style={{
            fontSize: '0.7rem', fontWeight: 700, color: '#64748b', textTransform: 'uppercase',
            letterSpacing: '0.08em', marginBottom: '1rem', display: 'flex', alignItems: 'center', gap: '0.4rem',
        }}>
            <span style={{ display: 'inline-block', width: 3, height: 12, background: accent, borderRadius: 2 }} />
            {children}
        </h3>
    );
}

/** Flattens a Laravel 422 body into the bullet list Blade rendered from $errors->all(). */
function messagesFrom(rejection) {
    const fromErrors = Object.values(rejection?.errors ?? {}).flat();

    return fromErrors.length ? fromErrors : [rejection?.message || 'Could not save that request.'];
}

/**
 * The MPR form, shared by the new-request and edit-request modals. Both Blade
 * components rendered the same three sections in the same chrome and differed
 * only in their header colours, their titles and — accidentally — in how much
 * of the form actually worked: the edit copy had a plain project select, a
 * free-text department and a free-text unit where the create copy had a
 * searchable picker and cascading selects. One component means that cannot
 * drift again; the per-modal identity comes in as props.
 */
export default function RequestModal({
    title, subtitle, gradient, accent, icon, submitLabel, initial,
    options, optionsError, onClose, onSubmit,
}) {
    // `initial` is read once, at mount: the provider mounts a keyed modal per
    // target and unmounts it on close, so reopening always starts from the
    // record. An effect that re-seeded from `initial` instead could wipe what
    // the user had typed whenever a late render changed its identity.
    const [values, setValues] = useState(initial);
    const [messages, setMessages] = useState([]);
    const [saving, setSaving] = useState(false);
    // Blade shipped one modal at 58rem with a hard three-column grid, so on a
    // phone the labels wrapped three deep and the values clipped. The form is
    // one tree rather than a desktop/mobile pair — two copies of a form this
    // long would drift — and the layout switches on the same 768px breakpoint
    // every page uses.
    const compact = useViewport() === 'mobile';

    useEffect(() => {
        const onKey = (event) => { if (event.key === 'Escape') onClose(); };
        document.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
        };
    }, [onClose]);

    const projects = options?.projects ?? [];
    const units = options?.units ?? [];
    const today = options?.today ?? '';

    const project = projects.find((p) => p.name === values.project_name);
    const locations = project?.locations ?? [];
    // Departments belong to a company, so choosing a project narrows them. With
    // no project — or a project with no company — the whole list stays offered.
    const departments = useMemo(() => {
        const all = options?.departments ?? [];

        return project?.company_id ? all.filter((d) => d.company_id === project.company_id) : all;
    }, [options, project]);

    function set(field, value) {
        setValues((current) => ({ ...current, [field]: value }));
    }

    async function submit(event) {
        event.preventDefault();
        setSaving(true);
        setMessages([]);
        try {
            await onSubmit({
                ...values,
                items: values.items.filter((row) => (row.description ?? '').trim() !== ''),
            });
            onClose();
        } catch (rejection) {
            setMessages(messagesFrom(rejection));
        } finally {
            setSaving(false);
        }
    }

    return (
        <div
            onClick={(event) => { if (event.target === event.currentTarget) onClose(); }}
            style={{
                display: 'flex', position: 'fixed', inset: 0, zIndex: 9999, alignItems: 'center',
                justifyContent: 'center', padding: '1rem', background: 'rgba(15,23,42,0.55)',
                backdropFilter: 'blur(3px)',
            }}
        >
            <div style={{
                width: '100%', maxWidth: compact ? '100%' : '58rem', maxHeight: compact ? '94vh' : '88vh',
                display: 'flex', flexDirection: 'column',
                background: '#fff', borderRadius: '1.25rem',
                boxShadow: '0 25px 60px -10px rgba(0,0,0,0.3), 0 10px 20px -5px rgba(0,0,0,0.15)',
            }}>
                <div style={{
                    flexShrink: 0, padding: compact ? '1rem 1.1rem' : '1.25rem 1.5rem',
                    borderRadius: '1.25rem 1.25rem 0 0',
                    background: gradient, display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.875rem' }}>
                        <div style={{ background: 'rgba(255,255,255,0.15)', borderRadius: '0.625rem', padding: '0.5rem' }}>
                            <svg style={{ width: '1.25rem', height: '1.25rem', stroke: '#fff' }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={icon} />
                            </svg>
                        </div>
                        <div>
                            <h2 style={{ color: '#fff', fontSize: '1rem', fontWeight: 700, lineHeight: 1.2 }}>{title}</h2>
                            <p style={{ color: '#bfdbfe', fontSize: '0.7rem', marginTop: '0.1rem' }}>{subtitle}</p>
                        </div>
                    </div>
                    <button
                        type="button" onClick={onClose} aria-label="Close"
                        style={{
                            color: '#fff', background: 'rgba(255,255,255,0.15)', border: 'none', borderRadius: '50%',
                            width: '2rem', height: '2rem', display: 'flex', alignItems: 'center',
                            justifyContent: 'center', cursor: 'pointer', fontSize: '1.25rem', lineHeight: 1,
                        }}
                    >
                        ×
                    </button>
                </div>

                <div style={{ flex: 1, overflowY: 'auto', padding: compact ? '1rem' : '1.5rem' }}>
                    {(messages.length > 0 || optionsError) && (
                        <div style={{
                            marginBottom: '1.25rem', padding: '0.875rem 1rem', background: '#fef2f2',
                            border: '1px solid #fecaca', borderRadius: '0.75rem', fontSize: '0.8rem', color: '#b91c1c',
                        }}>
                            <p style={{ fontWeight: 600, marginBottom: '0.25rem' }}>Please fix the following:</p>
                            <ul style={{ listStyle: 'disc', paddingLeft: '1.25rem', lineHeight: 1.8 }}>
                                {[...messages, optionsError].filter(Boolean).map((message) => (
                                    <li key={message}>{message}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <form id="mpr-form" onSubmit={submit}>
                        <div style={SECTION}>
                            <SectionTitle accent={accent}>Project / Department Details</SectionTitle>
                            <div style={{
                                display: 'grid',
                                gridTemplateColumns: compact ? '1fr' : 'repeat(3,minmax(0,1fr))',
                                gap: '1rem',
                            }}>
                                <div>
                                    <label className="form-label" htmlFor="mpr-date">
                                        Date <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        id="mpr-date" type="date" required className="form-input"
                                        value={values.date ?? ''} onChange={(e) => set('date', e.target.value)}
                                    />
                                </div>

                                <ProjectPicker
                                    projects={projects}
                                    value={values.project_name}
                                    onChange={(name) => setValues((current) => ({
                                        ...current,
                                        project_name: name,
                                        // A location belongs to one project, so it cannot survive
                                        // the project changing under it.
                                        location: '',
                                    }))}
                                />

                                <div>
                                    <label className="form-label" htmlFor="mpr-requested-by">
                                        Requested By <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        id="mpr-requested-by" type="text" required className="form-input"
                                        placeholder="Person's name"
                                        value={values.requested_by_name ?? ''}
                                        onChange={(e) => set('requested_by_name', e.target.value)}
                                    />
                                </div>

                                <UrgencyPicker
                                    value={values.required_date_text}
                                    onChange={(value) => set('required_date_text', value)}
                                />

                                <div>
                                    <label className="form-label" htmlFor="mpr-location">Location / Site</label>
                                    <select
                                        id="mpr-location" className="form-input"
                                        disabled={locations.length === 0}
                                        value={values.location ?? ''}
                                        onChange={(e) => set('location', e.target.value)}
                                    >
                                        <option value="">— Select Location —</option>
                                        {/* A location saved before it was deactivated, or under a
                                            project since renamed, stays visible instead of silently
                                            clearing on the next save. */}
                                        {values.location && !locations.includes(values.location) && (
                                            <option value={values.location}>{values.location}</option>
                                        )}
                                        {locations.map((name) => <option key={name} value={name}>{name}</option>)}
                                    </select>
                                </div>

                                <div>
                                    <label className="form-label" htmlFor="mpr-department">Department</label>
                                    <select
                                        id="mpr-department" className="form-input"
                                        value={values.department ?? ''}
                                        onChange={(e) => set('department', e.target.value)}
                                    >
                                        <option value="">— Select Department —</option>
                                        {values.department && !departments.some((d) => d.name === values.department) && (
                                            <option value={values.department}>{values.department}</option>
                                        )}
                                        {departments.map((d) => <option key={d.id} value={d.name}>{d.name}</option>)}
                                    </select>
                                </div>
                            </div>
                        </div>

                        <ItemRows
                            items={values.items} units={units} accent={accent} today={today} compact={compact}
                            onChange={(items) => set('items', items)}
                        />

                        <div style={{ ...SECTION, marginBottom: 0 }}>
                            <SectionTitle accent={accent}>Remarks / Notes</SectionTitle>
                            <textarea
                                rows={2} className="form-textarea" aria-label="Remarks"
                                value={values.remarks ?? ''} onChange={(e) => set('remarks', e.target.value)}
                            />
                        </div>
                    </form>
                </div>

                {/* On a phone the primary action is full width and pinned to the
                    bottom of the sheet, matching the mobile pages. */}
                <div style={{
                    flexShrink: 0, padding: compact ? '0.875rem 1rem' : '1rem 1.5rem',
                    borderTop: '1px solid #f1f5f9', borderRadius: '0 0 1.25rem 1.25rem', background: '#f8fafc',
                    display: 'flex', alignItems: 'center', gap: '0.75rem',
                    flexDirection: compact ? 'column-reverse' : 'row',
                }}>
                    <button
                        type="submit" form="mpr-form" className="btn-primary" disabled={saving}
                        style={compact ? { width: '100%', justifyContent: 'center' } : undefined}
                    >
                        {saving ? 'Saving…' : submitLabel}
                    </button>
                    <button
                        type="button" onClick={onClose} className="btn-secondary"
                        style={compact ? { width: '100%', justifyContent: 'center' } : undefined}
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    );
}

export { blankRow };
