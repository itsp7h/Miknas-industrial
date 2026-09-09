import { DevQuickLogin, FormError, LoginFields, RememberMe } from '../../../components/auth/LoginFields';
import useLogin from '../../../components/auth/useLogin';

/**
 * Mobile sign-in, following the mobile design language used elsewhere: a
 * gradient hero header, one field per row, and a full-width primary action
 * near the thumb rather than tucked to one side. Not the desktop page at a
 * narrower width (CLAUDE.md #12).
 */
export default function LoginPage({ redirectTo = '/app', showDevLogin = false, navigate = null }) {
    const f = useLogin({ redirectTo, navigate });

    return (
        <div style={{
            minHeight: '100vh', background: '#f1f5f9',
            display: 'flex', flexDirection: 'column',
        }}>
            {/* Hero header */}
            <div style={{
                position: 'relative', overflow: 'hidden',
                padding: '38px 22px 30px',
                background: 'linear-gradient(150deg,#0f172a 0%,#1e293b 55%,#1d4ed8 100%)',
                color: '#fff',
                borderBottomLeftRadius: 22, borderBottomRightRadius: 22,
            }}>
                <div style={{
                    position: 'absolute', top: -44, right: -44, width: 150, height: 150,
                    borderRadius: '9999px', background: 'rgba(255,255,255,.08)',
                }} />
                <div style={{ position: 'relative' }}>
                    <div style={{
                        width: 40, height: 40, borderRadius: 11, background: '#2563eb',
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                        fontWeight: 800, fontSize: 17, marginBottom: 14,
                    }}>
                        S
                    </div>
                    <div style={{ fontSize: 21, fontWeight: 700 }}>SteelERP</div>
                    <div style={{ fontSize: 12.5, color: '#cbd5e1', marginTop: 3 }}>
                        Manufacturing &amp; Trading
                    </div>
                </div>
            </div>

            {/* Form */}
            <div style={{
                flex: 1, display: 'flex', flexDirection: 'column',
                padding: '24px 18px 22px', boxSizing: 'border-box',
            }}>
                <h1 style={{ fontSize: 19, fontWeight: 700, color: '#0f172a', marginBottom: 4 }}>
                    Welcome back
                </h1>
                <p style={{ fontSize: 13.5, color: '#64748b', marginBottom: 20 }}>
                    Enter your credentials to continue.
                </p>

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

                    {/* Pushes the action to the bottom of the viewport when the
                        form is short, without pinning it over the content when
                        the keyboard is open. */}
                    <div style={{ flex: 1, minHeight: 12 }} />

                    <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={f.submitting}
                        style={{ width: '100%', justifyContent: 'center', minHeight: 46 }}
                    >
                        {f.submitting ? 'Signing in…' : 'Log in'}
                    </button>

                    <a
                        href="/forgot-password"
                        style={{
                            display: 'block', textAlign: 'center', marginTop: 14,
                            fontSize: 13.5, color: '#2563eb', textDecoration: 'none',
                        }}
                    >
                        Forgot your password?
                    </a>
                </form>

                {showDevLogin && <DevQuickLogin onFill={f.fillDemo} disabled={f.submitting} />}
            </div>
        </div>
    );
}
