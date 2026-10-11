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
    const compact = useViewport() === 'mobile';

    // On a phone, the mobile design's grouping: a coloured caps label above a
    // white card on the sheet's grey, rather than a bordered grey box.
    if (compact) {
        return (
            <section style={{ marginBottom: last ? 0 : 22 }}>
                <div style={{
                    display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                    padding: '0 4px', marginBottom: 8, minHeight: 24,
                }}>
                    <h3 style={{
                        margin: 0, fontSize: 13, fontWeight: 600, textTransform: 'uppercase',
                        letterSpacing: '0.06em', color: accent,
                    }}>
                        {title}
                    </h3>
                    {action}
                </div>
                <div style={{ background: '#FFFFFF', borderRadius: 18, padding: 16 }}>
                    {children}
                </div>
            </section>
        );
    }

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

const HEADER_BUTTON = {
    color: '#fff', background: 'rgba(255,255,255,0.15)', border: 'none', borderRadius: '50%',
    width: '2rem', height: '2rem', display: 'flex', alignItems: 'center',
    justifyContent: 'center', cursor: 'pointer', fontSize: '1.25rem', lineHeight: 1,
};

/**
 * `onMinimize`, when given, adds a minimize button to the header, and a click on
 * the backdrop or Escape minimizes instead of closing — a long form is not lost
 * to a stray click. `onCancel` is what Cancel and × do (a caller that asks
 * before discarding passes it); both fall back to `onClose`.
 */
export default function FormModal({
    title, subtitle, gradient, accent, icon,
    submitLabel, submitting = false, formId,
    messages = [], onClose, onMinimize, onCancel, maxWidth = '58rem', children,
}) {
    const compact = useViewport() === 'mobile';
    const dismiss = onMinimize ?? onClose;
    const cancel = onCancel ?? onClose;

    useEffect(() => {
        const onKey = (event) => { if (event.key === 'Escape') dismiss(); };
        document.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
        };
    }, [dismiss]);

    if (compact) {
        return (
            <PhoneSheet
                title={title} subtitle={subtitle} gradient={gradient} submitLabel={submitLabel}
                submitting={submitting} formId={formId} messages={messages}
                onMinimize={onMinimize} cancel={cancel}
            >
                {typeof children === 'function' ? children({ compact }) : children}
            </PhoneSheet>
        );
    }

    return (
        <div
            onClick={(event) => { if (event.target === event.currentTarget) dismiss(); }}
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
                    <div style={{ display: 'flex', gap: '0.5rem' }}>
                        {onMinimize && (
                            <button
                                type="button" onClick={onMinimize} aria-label="Minimize" title="Minimize — your entries are kept"
                                style={HEADER_BUTTON}
                            >
                                –
                            </button>
                        )}
                        <button type="button" onClick={cancel} aria-label="Close" style={HEADER_BUTTON}>
                            ×
                        </button>
                    </div>
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
                        type="button" onClick={cancel} className="btn-secondary"
                        style={compact ? { width: '100%', justifyContent: 'center' } : undefined}
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    );
}

/**
 * The phone layout (SteelERP-Mobile-Designs-V2's form sheets): the form
 * fills the screen under a coloured bar holding Cancel, the title and the
 * primary action, so the action is always in reach without scrolling to the
 * end of a long form. `m-ui` gives the fields the design's touch sizes.
 */
function PhoneSheet({ title, subtitle, gradient, submitLabel, submitting, formId, messages, onMinimize, cancel, children }) {
    const barButton = {
        background: 'none', border: 0, color: '#FFFFFF', font: 'inherit', fontSize: 17,
        padding: '10px 4px', cursor: 'pointer', whiteSpace: 'nowrap', minWidth: 64,
    };
    // "Submit Request" fits a desktop footer; the bar has room for a word.
    const short = /^save/i.test(submitLabel ?? '') ? 'Save' : (/^submit/i.test(submitLabel ?? '') ? 'Submit' : submitLabel);

    return (
        <div
            className="m-ui"
            role="dialog"
            aria-modal="true"
            aria-label={title}
            style={{
                position: 'fixed', inset: 0, zIndex: 9999, background: 'rgba(15,23,42,0.55)',
                display: 'flex', flexDirection: 'column', paddingTop: 'calc(env(safe-area-inset-top, 0px) + 12px)',
            }}
        >
            <div style={{
                flex: 1, minHeight: 0, display: 'flex', flexDirection: 'column', background: '#F1F5F9',
                borderRadius: '22px 22px 0 0', overflow: 'hidden', fontFamily: "'Inter', system-ui, sans-serif",
            }}>
                <div style={{ flexShrink: 0, background: gradient, color: '#FFFFFF', padding: '8px 12px 6px' }}>
                    <div aria-hidden="true" style={{ width: 36, height: 5, borderRadius: 3, background: 'rgba(255,255,255,0.45)', margin: '0 auto 2px' }} />
                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
                        <button type="button" onClick={cancel} style={{ ...barButton, textAlign: 'left' }}>Cancel</button>
                        <div style={{ minWidth: 0, textAlign: 'center' }}>
                            <h2 style={{ margin: 0, fontSize: 17, fontWeight: 600, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                                {title}
                            </h2>
                        </div>
                        <button
                            type="submit" form={formId} disabled={submitting}
                            style={{ ...barButton, fontWeight: 600, textAlign: 'right', opacity: submitting ? 0.6 : 1 }}
                        >
                            {submitting ? 'Saving…' : short}
                        </button>
                    </div>
                </div>

                <div style={{ flex: 1, overflowY: 'auto', padding: '16px 16px calc(32px + env(safe-area-inset-bottom, 0px))', WebkitOverflowScrolling: 'touch' }}>
                    {(subtitle || onMinimize) && (
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12, padding: '0 4px', marginBottom: 18 }}>
                            <span style={{ fontSize: 14, color: '#475569', lineHeight: 1.4 }}>{subtitle}</span>
                            {onMinimize && (
                                <button
                                    type="button" onClick={onMinimize}
                                    style={{ background: 'none', border: 0, color: '#2563EB', font: 'inherit', fontSize: 14, fontWeight: 500, cursor: 'pointer', flexShrink: 0 }}
                                >
                                    Minimize
                                </button>
                            )}
                        </div>
                    )}

                    {messages.length > 0 && (
                        <div role="alert" style={{
                            marginBottom: 18, padding: '12px 14px', background: '#FEF2F2',
                            borderRadius: 14, fontSize: 14, color: '#B91C1C',
                        }}>
                            <p style={{ fontWeight: 600, margin: '0 0 4px' }}>Please fix the following:</p>
                            <ul style={{ listStyle: 'disc', paddingLeft: '1.25rem', lineHeight: 1.7, margin: 0 }}>
                                {messages.map((message) => <li key={message}>{message}</li>)}
                            </ul>
                        </div>
                    )}

                    {children}
                </div>
            </div>
        </div>
    );
}
