import { AuthLink, FormError } from '../../../components/auth/AuthFields';
import { MobileAuthScreen, MobileSpacer } from '../../../components/auth/AuthScreen';
import { DevQuickLogin, LoginFields, RememberMe } from '../../../components/auth/LoginFields';
import useLogin from '../../../components/auth/useLogin';

/**
 * Mobile sign-in, from SteelERP-Mobile-Designs-V2: the brand on a dark field,
 * the form on a white sheet, remember-me and the reset link on one row, and
 * the primary action at the foot of the sheet near the thumb. Not the desktop
 * page at a narrower width (CLAUDE.md #12).
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
                    revealable
                />

                <div style={{
                    display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                    minHeight: 44, gap: 12, marginBottom: 18,
                }}>
                    <RememberMe
                        checked={f.values.remember}
                        onChange={f.setField}
                        disabled={f.submitting}
                    />
                    <AuthLink href="/forgot-password" style={{ fontSize: 15, fontWeight: 500 }}>
                        Forgot password?
                    </AuthLink>
                </div>

                <MobileSpacer />

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
        </MobileAuthScreen>
    );
}
