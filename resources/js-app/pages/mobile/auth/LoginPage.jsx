import { AuthLink, FormError } from '../../../components/auth/AuthFields';
import { MobileAuthScreen, MobileSpacer } from '../../../components/auth/AuthScreen';
import { DevQuickLogin, LoginFields, RememberMe } from '../../../components/auth/LoginFields';
import useLogin from '../../../components/auth/useLogin';

/**
 * Mobile sign-in: a gradient hero header, one field per row, and a full-width
 * primary action near the thumb rather than tucked to one side. Not the
 * desktop page at a narrower width (CLAUDE.md #12).
 */
export default function LoginPage({ redirectTo = '/app', showDevLogin = false, navigate = null }) {
    const f = useLogin({ redirectTo, navigate });

    return (
        <MobileAuthScreen title="Welcome back" subtitle="Enter your credentials to continue.">
            <FormError message={f.formError} />

            <form
                onSubmit={f.submit}
                noValidate
                style={{ display: 'flex', flexDirection: 'column', flex: 1 }}
            >
                <LoginFields
                    values={f.values}
                    errors={f.errors}
                    onChange={f.setField}
                    disabled={f.submitting}
                    autoFocus={false}
                />

                <div style={{ marginBottom: 18 }}>
                    <RememberMe
                        checked={f.values.remember}
                        onChange={f.setField}
                        disabled={f.submitting}
                    />
                </div>

                <MobileSpacer />

                <button
                    type="submit"
                    className="btn btn-primary"
                    disabled={f.submitting}
                    style={{ width: '100%', justifyContent: 'center', minHeight: 46 }}
                >
                    {f.submitting ? 'Signing in…' : 'Log in'}
                </button>

                <AuthLink
                    href="/forgot-password"
                    style={{ display: 'block', textAlign: 'center', marginTop: 14, fontSize: 13.5 }}
                >
                    Forgot your password?
                </AuthLink>
            </form>

            {showDevLogin && <DevQuickLogin onFill={f.fillDemo} disabled={f.submitting} />}
        </MobileAuthScreen>
    );
}
