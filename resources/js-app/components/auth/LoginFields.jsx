/**
 * The parts of the login form that must not drift between desktop and mobile:
 * the two inputs, their error text, remember-me, and the dev quick-fill.
 * Layout, spacing and the submit button stay with each page (CLAUDE.md #12 —
 * separate files per viewport), so only the fields themselves are shared.
 *
 * FormError moved to AuthFields when the other four auth screens were
 * converted — it is not login's any more — and is re-exported here so the
 * pages that already import it from this module keep working.
 */

import { useState } from 'react';

export { FormError } from './AuthFields';

/**
 * `revealable` adds the phone design's eye button to the password field: on a
 * touch keyboard a mistyped password is far likelier, and far harder to see.
 */
export function LoginFields({ values, errors, onChange, disabled, autoFocus = true, revealable = false }) {
    const [shown, setShown] = useState(false);

    return (
        <>
            <div style={{ marginBottom: 16 }}>
                <label className="form-label" htmlFor="email">Email</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    autoComplete="username"
                    autoFocus={autoFocus}
                    required
                    disabled={disabled}
                    className={`form-input${errors.email ? ' form-input-error' : ''}`}
                    style={{ width: '100%' }}
                    value={values.email}
                    onChange={(e) => onChange('email', e.target.value)}
                />
                {errors.email && (
                    <p style={{ marginTop: 6, fontSize: 13, color: '#dc2626' }}>{errors.email}</p>
                )}
            </div>

            <div style={{ marginBottom: 16 }}>
                <label className="form-label" htmlFor="password">Password</label>
                <div style={{ position: 'relative' }}>
                    <input
                        id="password"
                        name="password"
                        type={revealable && shown ? 'text' : 'password'}
                        autoComplete="current-password"
                        required
                        disabled={disabled}
                        className={`form-input${errors.password ? ' form-input-error' : ''}`}
                        style={{ width: '100%', ...(revealable ? { paddingRight: 52 } : {}) }}
                        value={values.password}
                        onChange={(e) => onChange('password', e.target.value)}
                    />
                    {revealable && (
                        <button
                            type="button"
                            aria-label={shown ? 'Hide password' : 'Show password'}
                            aria-pressed={shown}
                            onClick={() => setShown((v) => !v)}
                            style={{
                                position: 'absolute', right: 4, top: '50%', transform: 'translateY(-50%)',
                                width: 44, height: 44, border: 0, background: 'transparent', color: '#475569',
                                display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer',
                            }}
                        >
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                <path d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12z" /><circle cx="12" cy="12" r="3" />
                                {shown && <path d="M4 4l16 16" />}
                            </svg>
                        </button>
                    )}
                </div>
                {errors.password && (
                    <p style={{ marginTop: 6, fontSize: 13, color: '#dc2626' }}>{errors.password}</p>
                )}
            </div>
        </>
    );
}

export function RememberMe({ checked, onChange, disabled }) {
    return (
        <label
            htmlFor="remember"
            style={{ display: 'inline-flex', alignItems: 'center', gap: 8, cursor: 'pointer' }}
        >
            <input
                id="remember"
                name="remember"
                type="checkbox"
                disabled={disabled}
                checked={checked}
                onChange={(e) => onChange('remember', e.target.checked)}
                style={{ width: 16, height: 16, accentColor: '#2563eb', cursor: 'pointer' }}
            />
            <span style={{ fontSize: 14, color: '#475569' }}>Remember me</span>
        </label>
    );
}

/**
 * Local-environment convenience carried over from the Blade page. The host
 * view only renders the flag outside production, so this never reaches a
 * deployed login screen.
 */
export function DevQuickLogin({ onFill, disabled }) {
    return (
        <div
            style={{
                marginTop: 24, padding: '14px 16px', borderRadius: 8,
                background: '#f0f9ff', border: '1px solid #bae6fd', color: '#0369a1',
            }}
        >
            <div style={{
                fontWeight: 700, marginBottom: 8, fontSize: 12,
                textTransform: 'uppercase', letterSpacing: '0.05em',
            }}>
                Dev Quick Login
            </div>
            <button
                type="button"
                disabled={disabled}
                onClick={() => onFill('admin@erp.com', 'password')}
                style={{
                    padding: '5px 12px', background: '#0ea5e9', color: '#fff',
                    border: 'none', borderRadius: 6, cursor: 'pointer',
                    fontSize: 12, fontWeight: 600,
                }}
            >
                Admin
            </button>
            <div style={{ marginTop: 8, color: '#64748b', fontSize: 11 }}>
                Password for all accounts:{' '}
                <code style={{ background: '#e0f2fe', padding: '1px 5px', borderRadius: 3 }}>
                    password
                </code>
            </div>
        </div>
    );
}
