import { SubmitButton, TextField } from './AuthFields';

/**
 * The reset form's three inputs and its action, shared by the two viewports
 * so the rules cannot drift: the address comes from the emailed link and the
 * new password has to be typed twice.
 */
export function ResetPasswordFields({ values, errors, setField, submitting, compact }) {
    return (
        <>
            <TextField
                id="email"
                label="Email"
                type="email"
                autoComplete="username"
                autoFocus={!compact}
                value={values.email}
                error={errors.email}
                disabled={submitting}
                onChange={(value) => setField('email', value)}
            />
            <TextField
                id="password"
                label="New password"
                type="password"
                autoComplete="new-password"
                value={values.password}
                error={errors.password}
                disabled={submitting}
                onChange={(value) => setField('password', value)}
                hint="At least 8 characters."
            />
            <TextField
                id="password_confirmation"
                label="Confirm new password"
                type="password"
                autoComplete="new-password"
                value={values.password_confirmation}
                error={errors.password_confirmation}
                disabled={submitting}
                onChange={(value) => setField('password_confirmation', value)}
            />
        </>
    );
}

/**
 * A reset does not sign anyone in — Breeze sent them back to the login page
 * with a flash, which a fetch cannot carry across a full page load. So the
 * outcome is stated here instead, with the way onward.
 */
export function ResetDone({ message, compact }) {
    return (
        <div>
            <div
                role="status"
                style={{
                    marginBottom: 20, padding: '12px 14px', borderRadius: 8,
                    background: '#f0fdf4', border: '1px solid #bbf7d0',
                    color: '#15803d', fontSize: 13.5,
                }}
            >
                {message || 'Your password has been reset.'}
            </div>
            <a
                href="/login"
                className="btn btn-primary"
                style={{
                    width: '100%', justifyContent: 'center', textDecoration: 'none',
                    ...(compact ? { minHeight: 46 } : {}),
                }}
            >
                Continue to sign in
            </a>
        </div>
    );
}

export { SubmitButton };
