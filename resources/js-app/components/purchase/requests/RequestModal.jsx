import { useMemo, useState } from 'react';
import useViewport from '../../../hooks/useViewport';
import FormModal, { FormSection } from '../../ui/FormModal';
import ItemRows, { blankRow } from './ItemRows';
import ProjectPicker from './ProjectPicker';
import UrgencyPicker from './UrgencyPicker';

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
        <FormModal
            title={title} subtitle={subtitle} gradient={gradient} accent={accent} icon={icon}
            submitLabel={submitLabel} submitting={saving} formId="mpr-form"
            messages={[...messages, optionsError].filter(Boolean)}
            onClose={onClose}
        >
            <form id="mpr-form" onSubmit={submit}>
                <FormSection accent={accent} title="Project / Department Details">
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
                </FormSection>

                <ItemRows
                    items={values.items} units={units} accent={accent} today={today} compact={compact}
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
    );
}

export { blankRow };
