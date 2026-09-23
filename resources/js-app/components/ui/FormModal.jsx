import { useEffect } from 'react';
import useViewport from '../../hooks/useViewport';

/**
 * The dialog the MPR form is drawn in, lifted out of RequestModal so other
 * long forms can be the same thing rather than an approximation of it.
 *
 * It is deliberately not `ui/Modal`. That one is a small centred box for a
 * handful of fields; this is the full-height gradient-headed sheet the
 * pipeline uses, with a scrolling body and a pinned footer — a form of a
 * dozen-plus fields needs somewhere to put its identity and somewhere for the
 * primary action to stay put. Copying its 100 lines into each such form is
 * how the two Blade MPR modals drifted apart in the first place
 * (CLAUDE.md #10), so they share this instead.
 *
 * One tree with a `compact` flag from `useViewport()`, not a desktop/mobile
 * pair: there is one dialog here, laid out two ways.
 */

/**
 * The line-item table look: borderless inputs sitting inside bordered cells,
 * as Blade drew the MPR's material table. Shared so a second line-item table
 * — the purchase order's — is the same table rather than a near-miss.
 */
export const TABLE_HEAD = {
    border: '1px solid #e2e8f0', padding: '0.5rem 0.625rem', textAlign: 'left',
    fontSize: '0.65rem', fontWeight: 600, color: '#94a3b8', textTransform: 'uppercase',
};
export const TABLE_CELL = { border: '1px solid #e2e8f0', padding: '0.25rem 0.5rem' };
export const TABLE_FIELD = { width: '100%', border: 0, outline: 'none', fontSize: '0.8rem', background: 'transparent' };


/**
 * Flattens a Laravel 422 body into the bullet list the dialog shows at the
 * top. Anything that is not a 422 falls back to `fallback`, so a 500 or a
 * dropped connection reports instead of leaving the dialog silent — which is
 * what every one of these forms used to do.
 */
export function messagesFrom(rejection, fallback) {
    const fromErrors = Object.values(rejection?.errors ?? {}).flat();

    return fromErrors.length ? fromErrors : [rejection?.message || fallback];
}

/** The same 422 body, keyed by field, first message only. */
export function fieldErrors(rejection) {
    return Object.fromEntries(
        Object.entries(rejection?.errors ?? {}).map(([key, list]) => [
            key, Array.isArray(list) ? list[0] : String(list),
        ])
    );
}

/**
 * A labelled control in the dialog's idiom: `.form-label` with a required
 * marker, `.form-input`/`.form-select`/`.form-textarea`, the field's error
 * beneath it, or a hint when there is no error.
 *
 * Pass `children` to supply a bespoke control and keep the labelling; pass
 * `type`/`options` to have the usual one rendered.
 */
export function Field({
    idPrefix, label, name, values, errors = {}, onChange,
    type = 'text', required = false, placeholder, hint, options, rows = 3,
    span = 1, disabled = false, children,
}) {
    const id = `${idPrefix}-${name}`;
    const error = errors[name];
    const cls = (base) => `${base}${error ? ' form-input-error' : ''}`;

    const shared = {
        id,
        name,
        value: values?.[name] ?? '',
        placeholder,
        disabled,
        required,
        'aria-invalid': error ? true : undefined,
        onChange: (e) => onChange(name, e.target.value),
    };

    return (
        <div style={{ gridColumn: span > 1 ? `span ${span}` : undefined }}>
            <label className="form-label" htmlFor={id}>
                {label} {required && <span className="text-red-500">*</span>}
            </label>

            {children ?? (options ? (
                <select {...shared} className={cls('form-select')}>
                    {options.map((option) => (
                        <option key={option.value} value={option.value}>{option.label}</option>
                    ))}
                </select>
            ) : type === 'textarea' ? (
                <textarea {...shared} rows={rows} className={cls('form-textarea')} />
            ) : (
                <input {...shared} type={type} className={cls('form-input')} />
            ))}

            {error && <p style={{ marginTop: 4, fontSize: '0.75rem', color: '#dc2626' }}>{error}</p>}
            {!error && hint && (
                <p style={{ marginTop: 4, fontSize: '0.7rem', color: '#94a3b8' }}>{hint}</p>
            )}
        </div>
    );
}

/** A read-only value shown where a field would be — a computed total, a fixed record. */
export function ReadOnlyField({ label, children, hint }) {
    return (
        <div>
            <span className="form-label">{label}</span>
            <div style={{
                border: '1px solid #e2e8f0', background: '#fff', borderRadius: '0.5rem',
                padding: '0.5rem 0.75rem', fontSize: '0.875rem', fontWeight: 600, color: '#0f172a',
            }}>
                {children}
            </div>
            {hint && <p style={{ marginTop: 4, fontSize: '0.7rem', color: '#94a3b8' }}>{hint}</p>}
        </div>
    );
}

export function SectionTitle({ accent, children }) {
    return (
        <h3 style={{
            fontSize: '0.7rem', fontWeight: 700, color: '#64748b', textTransform: 'uppercase',
            letterSpacing: '0.08em', display: 'flex', alignItems: 'center', gap: '0.4rem',
        }}>
            <span style={{ display: 'inline-block', width: 3, height: 12, background: accent, borderRadius: 2 }} />
            {children}
        </h3>
    );
}

/** A titled card. `action` sits opposite the title — e.g. "+ Add Row". */
export function FormSection({ accent, title, action = null, last = false, children }) {
    return (
        <section style={{
            background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '0.875rem',
            padding: '1.25rem', marginBottom: last ? 0 : '1.25rem',
        }}>
            <div style={{
                display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                marginBottom: '1rem',
            }}>
                <SectionTitle accent={accent}>{title}</SectionTitle>
                {action}
            </div>
            {children}
        </section>
    );
}

export default function FormModal({
    title, subtitle, gradient, accent, icon,
    submitLabel, submitting = false, formId,
    messages = [], onClose, maxWidth = '58rem', children,
}) {
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
                width: '100%', maxWidth: compact ? '100%' : maxWidth,
                maxHeight: compact ? '94vh' : '88vh',
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
                            {subtitle && (
                                <p style={{ color: '#bfdbfe', fontSize: '0.7rem', marginTop: '0.1rem' }}>{subtitle}</p>
                            )}
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
                    {messages.length > 0 && (
                        <div role="alert" style={{
                            marginBottom: '1.25rem', padding: '0.875rem 1rem', background: '#fef2f2',
                            border: '1px solid #fecaca', borderRadius: '0.75rem', fontSize: '0.8rem', color: '#b91c1c',
                        }}>
                            <p style={{ fontWeight: 600, marginBottom: '0.25rem' }}>Please fix the following:</p>
                            <ul style={{ listStyle: 'disc', paddingLeft: '1.25rem', lineHeight: 1.8 }}>
                                {messages.map((message) => <li key={message}>{message}</li>)}
                            </ul>
                        </div>
                    )}

                    {typeof children === 'function' ? children({ compact }) : children}
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
                        type="submit" form={formId} className="btn-primary" disabled={submitting}
                        style={compact ? { width: '100%', justifyContent: 'center' } : undefined}
                    >
                        {submitting ? 'Saving…' : submitLabel}
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
