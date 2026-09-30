import { useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import useViewport from '../../../hooks/useViewport';
import FormModal, { FormSection } from '../../ui/FormModal';
import ConfirmDialog from '../../ui/ConfirmDialog';
import ItemRows, { blankRow } from './ItemRows';
import CompanyPicker from './CompanyPicker';
import ProjectPicker from './ProjectPicker';
import UrgencyPicker from './UrgencyPicker';

/** Flattens a Laravel 422 body into the bullet list Blade rendered from $errors->all(). */
function messagesFrom(rejection) {
    const fromErrors = Object.values(rejection?.errors ?? {}).flat();

    return fromErrors.length ? fromErrors : [rejection?.message || 'Could not save that request.'];
}

/** The names System → Requested By maps to a company, in list order. */
function requestersFor(requesters, companyId) {
    return requesters
        .filter((person) => (person.company_ids ?? []).includes(companyId))
        .map((person) => person.name);
}

/**
 * The form while minimized: a bar in the provider's dock, bottom-right, that
 * brings it back as it was left. The form's state lives in RequestModal, which
 * stays mounted underneath, so nothing typed is lost. `label` says which draft
 * it is — its company, or its first item — so several can be told apart.
 */
function MinimizedBar({ title, label, gradient, icon, itemCount, onRestore, onDiscard }) {
    return (
        <div
            role="region" aria-label={`${title} (minimized)`}
            style={{
                pointerEvents: 'auto', display: 'flex', alignItems: 'center', gap: '0.5rem',
                width: '17rem', maxWidth: '100%',
                background: gradient, color: '#fff', borderRadius: '0.875rem', padding: '0.5rem 0.5rem 0.5rem 0.875rem',
                boxShadow: '0 12px 30px -8px rgba(0,0,0,0.35)',
            }}
        >
            <button
                type="button" onClick={onRestore} aria-label={`Restore ${title}`} title="Click to continue"
                style={{
                    flex: 1, display: 'flex', alignItems: 'center', gap: '0.625rem', background: 'none', border: 'none',
                    color: '#fff', cursor: 'pointer', textAlign: 'left', minWidth: 0, padding: 0,
                }}
            >
                <svg style={{ width: '1.1rem', height: '1.1rem', flexShrink: 0 }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={icon} />
                </svg>
                <span style={{ minWidth: 0 }}>
                    <span style={{ display: 'block', fontSize: '0.8rem', fontWeight: 700, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                        {title}
                    </span>
                    <span style={{ display: 'block', fontSize: '0.7rem', opacity: 0.85, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                        {label || 'draft'}{itemCount ? ` · ${itemCount} item${itemCount === 1 ? '' : 's'}` : ''}
                    </span>
                </span>
            </button>
            <button
                type="button" onClick={onDiscard} aria-label={`Discard ${title}`} title="Discard"
                style={{
                    flexShrink: 0, background: 'rgba(255,255,255,0.18)', border: 'none', color: '#fff',
                    borderRadius: '50%', width: '1.75rem', height: '1.75rem', cursor: 'pointer', fontSize: '1.1rem', lineHeight: 1,
                }}
            >
                ×
            </button>
        </div>
    );
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
    minimized = false, onMinimize, onRestore, dock = null,
}) {
    // `initial` is read once, at mount: the provider mounts a keyed modal per
    // target and unmounts it on close, so reopening always starts from the
    // record. An effect that re-seeded from `initial` instead could wipe what
    // the user had typed whenever a late render changed its identity.
    const [values, setValues] = useState(initial);
    const [messages, setMessages] = useState([]);
    const [saving, setSaving] = useState(false);
    // 'submit' | 'discard' while its confirmation is showing.
    const [confirming, setConfirming] = useState(null);
    // Blade shipped one modal at 58rem with a hard three-column grid, so on a
    // phone the labels wrapped three deep and the values clipped. The form is
    // one tree rather than a desktop/mobile pair — two copies of a form this
    // long would drift — and the layout switches on the same 768px breakpoint
    // every page uses.
    const compact = useViewport() === 'mobile';

    const companies = options?.companies ?? [];
    const allProjects = options?.projects ?? [];
    const units = options?.units ?? [];
    const catalogue = options?.items ?? [];
    const requesters = options?.requesters ?? [];
    const today = options?.today ?? '';

    // A request belongs to a company and, usually, to a project within it.
    const company = companies.find((c) => c.name === values.company_name);
    // Only the chosen company's projects, so nobody files against another
    // company's site.
    const projects = useMemo(
        () => (company?.id ? allProjects.filter((p) => p.company_id === company.id) : []),
        [allProjects, company]
    );
    const project = projects.find((p) => p.name === values.project_name);
    // Locations belong to a project. With none chosen the company's own list —
    // every location under its projects — keeps the field usable.
    const locations = project?.locations ?? company?.locations ?? [];
    // A department belongs to one company, so there is nothing sensible to
    // offer until a company is chosen — listing every company's departments
    // invites filing a request against a department that is not theirs.
    const departments = useMemo(() => {
        if (! company?.id) return [];

        return (options?.departments ?? []).filter((d) => d.company_id === company.id);
    }, [options, company]);
    // Requested By follows the company the same way: System → Requested By
    // maps each person to the companies they raise requests for.
    const offeredRequesters = useMemo(
        () => (company?.id ? requestersFor(requesters, company.id) : []),
        [requesters, company]
    );

    /**
     * What choosing a company settles on its own.
     *
     * A field with exactly one option is not a choice, so it is filled: one
     * project, one department, one location. It cascades — a company with a
     * single project takes that project's locations, not the company's whole
     * list, so a lone location beneath it is filled too.
     *
     * With more than one on offer nothing is picked, because choosing for
     * someone would put a project or a site on the request that nobody chose.
     */
    function settledFor(companyName) {
        const chosen = companies.find((c) => c.name === companyName);
        const theirProjects = chosen ? allProjects.filter((p) => p.company_id === chosen.id) : [];
        const onlyProject = theirProjects.length === 1 ? theirProjects[0] : null;
        const offeredLocations = onlyProject ? (onlyProject.locations ?? []) : (chosen?.locations ?? []);
        const theirDepartments = chosen
            ? (options?.departments ?? []).filter((d) => d.company_id === chosen.id)
            : [];

        const theirRequesters = chosen ? requestersFor(requesters, chosen.id) : [];

        return {
            company_name: companyName,
            project_name: onlyProject?.name ?? '',
            location: offeredLocations.length === 1 ? offeredLocations[0] : '',
            department: theirDepartments.length === 1 ? theirDepartments[0].name : '',
            requested_by_name: theirRequesters.length === 1 ? theirRequesters[0] : '',
        };
    }

    function set(field, value) {
        setValues((current) => ({ ...current, [field]: value }));
    }

    // Anything typed or chosen since the form opened. Discarding an untouched
    // form loses nothing, so it closes without asking.
    const dirty = JSON.stringify(values) !== JSON.stringify(initial);
    const filledItems = values.items.filter((row) => (row.description ?? '').trim() !== '').length;

    function requestDiscard() {
        if (dirty) setConfirming('discard');
        else onClose();
    }

    // The browser has checked the required fields by the time this runs, so
    // the question is only ever asked of a form that can be sent.
    function submit(event) {
        event.preventDefault();
        setConfirming('submit');
    }

    async function save() {
        setSaving(true);
        setMessages([]);
        try {
            await onSubmit({
                ...values,
                items: values.items.filter((row) => (row.description ?? '').trim() !== ''),
            });
            onClose();
        } catch (rejection) {
            setConfirming(null);
            setMessages(messagesFrom(rejection));
        } finally {
            setSaving(false);
        }
    }

    const creating = submitLabel === 'Submit Request';
    const dialogs = (
        <>
            <ConfirmDialog
                open={confirming === 'submit'}
                title={creating ? 'Submit this request?' : 'Save your changes?'}
                body={creating
                    ? `It will be sent for approval${filledItems ? ` with ${filledItems} item${filledItems === 1 ? '' : 's'}` : ''}.`
                    : `${subtitle ? `${subtitle} will be updated` : 'The request will be updated'} with what you have entered.`}
                confirmLabel={saving ? 'Saving…' : submitLabel}
                cancelLabel="Review again"
                busy={saving}
                onConfirm={save}
                onCancel={() => setConfirming(null)}
            />
            <ConfirmDialog
                open={confirming === 'discard'}
                title={creating ? 'Discard this request?' : 'Discard your changes?'}
                body="What you have entered will be lost."
                confirmLabel="Discard"
                cancelLabel="Keep editing"
                tone="danger"
                onConfirm={() => { setConfirming(null); onClose(); }}
                onCancel={() => setConfirming(null)}
            />
        </>
    );

    if (minimized) {
        const firstItem = values.items.find((row) => (row.description ?? '').trim() !== '')?.description;
        const bar = (
            <MinimizedBar
                title={title} gradient={gradient} icon={icon} itemCount={filledItems}
                label={[creating ? null : subtitle, values.company_name || firstItem].filter(Boolean).join(' · ')}
                onRestore={onRestore} onDiscard={requestDiscard}
            />
        );

        return (
            <>
                {dock ? createPortal(bar, dock) : bar}
                {dialogs}
            </>
        );
    }

    return (
        <>
        {dialogs}
        <FormModal
            title={title} subtitle={subtitle} gradient={gradient} accent={accent} icon={icon}
            submitLabel={submitLabel} submitting={saving} formId="mpr-form"
            messages={[...messages, optionsError].filter(Boolean)}
            onClose={onClose}
            onMinimize={onMinimize}
            onCancel={requestDiscard}
        >
            <form id="mpr-form" onSubmit={submit}>
                <FormSection accent={accent} title="Company / Department Details">
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

                        <CompanyPicker
                            companies={companies}
                            value={values.company_name}
                            onChange={(name) => setValues((current) => ({
                                // The project belongs to the old company, the department
                                // and the location to the old project, so none of them
                                // survives the company changing under them — they are
                                // replaced wholesale by whatever the new one settles.
                                ...current,
                                ...settledFor(name),
                            }))}
                        />

                        <ProjectPicker
                            projects={projects}
                            disabled={!company}
                            value={values.project_name}
                            onChange={(name) => setValues((current) => {
                                // Where the new project offers exactly one location there
                                // is no choice to make, so make it; with several, choosing
                                // for the user would put a site nobody picked on the
                                // request. Clearing the project falls back to the
                                // company's own list.
                                const offered = name
                                    ? (projects.find((p) => p.name === name)?.locations ?? [])
                                    : (company?.locations ?? []);

                                return {
                                    ...current,
                                    project_name: name,
                                    location: offered.length === 1 ? offered[0] : '',
                                };
                            })}
                        />

                        <div>
                            <label className="form-label" htmlFor="mpr-requested-by">
                                Requested By <span className="text-red-500">*</span>
                            </label>
                            <select
                                id="mpr-requested-by" required className="form-input"
                                disabled={!company && !values.requested_by_name}
                                value={values.requested_by_name ?? ''}
                                onChange={(e) => set('requested_by_name', e.target.value)}
                            >
                                <option value="">{company ? '— Select Person —' : '— Select a company first —'}</option>
                                {/* A name typed before this was a picker, or a person
                                    since renamed, removed or unmapped from this company,
                                    stays selectable rather than silently emptying the
                                    field on the next save. */}
                                {values.requested_by_name && !offeredRequesters.includes(values.requested_by_name) && (
                                    <option value={values.requested_by_name}>{values.requested_by_name}</option>
                                )}
                                {offeredRequesters.map((name) => <option key={name} value={name}>{name}</option>)}
                            </select>
                            {company && offeredRequesters.length === 0 && (
                                <p style={{ fontSize: 12, color: '#b45309', marginTop: 4 }}>
                                    No one is set up for {company.name} yet. Add them under System → Requested By.
                                </p>
                            )}
                        </div>

                        <UrgencyPicker
                            value={values.required_date_text}
                            onChange={(value) => set('required_date_text', value)}
                        />

                        <div>
                            <label className="form-label" htmlFor="mpr-location">Location / Project</label>
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

                        {/* Last of seven in a three-column grid, so on its own row.
                            Spanning it fills the space rather than leaving two
                            thirds of the row empty beside a short select. */}
                        <div style={{ gridColumn: compact ? 'auto' : '1 / -1' }}>
                            <label className="form-label" htmlFor="mpr-department">Department</label>
                            <select
                                id="mpr-department" className="form-input" style={{ width: '100%' }}
                                disabled={!company}
                                value={values.department ?? ''}
                                onChange={(e) => set('department', e.target.value)}
                            >
                                <option value="">
                                    {company ? '— Select Department —' : '— Choose a company first —'}
                                </option>
                                {/* A department saved before its company was known, or
                                    since deactivated, stays visible instead of the form
                                    silently dropping it. */}
                                {values.department && !departments.some((d) => d.name === values.department) && (
                                    <option value={values.department}>{values.department}</option>
                                )}
                                {departments.map((d) => <option key={d.id} value={d.name}>{d.name}</option>)}
                            </select>
                        </div>
                    </div>
                </FormSection>

                <ItemRows
                    items={values.items} units={units} catalogue={catalogue}
                    accent={accent} today={today} compact={compact}
                    onChange={(items) => set('items', items)}
                />

                <FormSection accent={accent} title="Remarks / Notes" last>
                    <textarea
                        rows={2} className="form-textarea" aria-label="Remarks"
                        value={values.remarks ?? ''} onChange={(e) => set('remarks', e.target.value)}
                    />
                </FormSection>
            </form>
        </FormModal>
        </>
    );
}

export { blankRow };
