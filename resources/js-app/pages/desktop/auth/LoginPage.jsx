import { AuthLink, FormError } from '../../../components/auth/AuthFields';
import { DesktopAuthScreen } from '../../../components/auth/AuthScreen';
import { DevQuickLogin, LoginFields, RememberMe } from '../../../components/auth/LoginFields';
import useLogin from '../../../components/auth/useLogin';

/**
 * Desktop sign-in. The brand panel beside the form is DesktopAuthScreen,
 * shared with the other four auth screens; the mobile counterpart is a
 * separate file (CLAUDE.md #12).
 */
export default function LoginPage({ redirectTo = '/app', showDevLogin = false, navigate = null }) {
    const f = useLogin({ redirectTo, navigate });

    return (
        <DesktopAuthScreen
            title="Welcome back"
            subtitle="Enter your credentials to continue."
            blurb="Sign in to reach your dashboard."
        >
            <FormError message={f.formError} />

            <form onSubmit={f.submit} noValidate>
                <LoginFields
                    values={f.values}
                    errors={f.errors}
                    onChange={f.setField}
                    disabled={f.submitting}
                />

                <div style={{
                    display: 'flex', alignItems: 'center',
                    justifyContent: 'space-between', marginBottom: 22,
                }}>
                    <RememberMe
                        checked={f.values.remember}
                        onChange={f.setField}
                        disabled={f.submitting}
                    />
                    <AuthLink href="/forgot-password">Forgot your password?</AuthLink>
                </div>

                <button
                    type="submit"
                    className="btn btn-primary"
                    disabled={f.submitting}
                    style={{ width: '100%', justifyContent: 'center' }}
                >
                    {f.submitting ? 'Signing in…' : 'Log in'}
                </button>
            </form>

            {showDevLogin && <DevQuickLogin onFill={f.fillDemo} disabled={f.submitting} />}
        </DesktopAuthScreen>
    );
}
