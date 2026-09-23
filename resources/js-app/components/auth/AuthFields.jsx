/**
 * The field vocabulary the auth screens share. Layout stays with each page
 * (CLAUDE.md #12) — this is what a labelled input, an error and a primary
 * action look like, so five screens cannot each invent their own.
 */

export function FormError({ message }) {
    if (!message) return null;

    return (
        <div
            role="alert"
            style={{
                marginBottom: 16, padding: '10px 12px', borderRadius: 8,
                background: '#fef2f2', border: '1px solid #fecaca',
                color: '#b91c1c', fontSize: 13,
            }}
        >
            {message}
        </div>
    );
}

/**
 * The counterpart to FormError: "we sent the link", "we sent another one".
 * `role="status"` rather than `role="alert"` — it is not an interruption.
 */
export function StatusMessage({ message }) {
    if (!message) return null;

    return (
        <div
            role="status"
            style={{
                marginBottom: 16, padding: '10px 12px', borderRadius: 8,
                background: '#f0fdf4', border: '1px solid #bbf7d0',
                color: '#15803d', fontSize: 13,
            }}
        >
            {message}
        </div>
    );
}

export function TextField({
    id, label, type = 'text', value, onChange, error, disabled,
    autoComplete, autoFocus = false, readOnly = false, hint = null,
}) {
    return (
        <div style={{ marginBottom: 16 }}>
            <label className="form-label" htmlFor={id}>{label}</label>
            <input
                id={id}
                name={id}
                type={type}
                autoComplete={autoComplete}
                autoFocus={autoFocus}
                readOnly={readOnly}
                required
                disabled={disabled}
                className={`form-input${error ? ' form-input-error' : ''}`}
                style={{ width: '100%', ...(readOnly ? { background: '#f8fafc', color: '#64748b' } : {}) }}
                value={value}
                onChange={(e) => onChange(e.target.value)}
            />
            {error && <p style={{ marginTop: 6, fontSize: 13, color: '#dc2626' }}>{error}</p>}
            {!error && hint && (
                <p style={{ marginTop: 6, fontSize: 12, color: '#94a3b8' }}>{hint}</p>
            )}
        </div>
    );
}

export function SubmitButton({ children, busyLabel, submitting, disabled = false, style = {} }) {
    return (
        <button
            type="submit"
            className="btn btn-primary"
            disabled={submitting || disabled}
            style={{ width: '100%', justifyContent: 'center', ...style }}
        >
            {submitting ? busyLabel : children}
        </button>
    );
}

export function AuthLink({ href, children, style = {} }) {
    return (
        <a
            href={href}
            style={{ fontSize: 13, color: '#2563eb', textDecoration: 'none', ...style }}
        >
            {children}
        </a>
    );
}
