import { DevQuickLogin, FormError, LoginFields, RememberMe } from '../../../components/auth/LoginFields';
import useLogin from '../../../components/auth/useLogin';

/**
 * Desktop sign-in: a brand panel beside the form, echoing the shell's navy
 * sidebar so the app does not change identity at the door. The mobile
 * counterpart is a separate file (CLAUDE.md #12).
 */
export default function LoginPage({ redirectTo = '/app', showDevLogin = false, navigate = null }) {
    const f = useLogin({ redirectTo, navigate });

    return (
        <div style={{
            minHeight: '100vh', display: 'flex', alignItems: 'center',
            justifyContent: 'center', background: '#f1f5f9', padding: 24,
        }}>
            <div style={{
                display: 'grid', gridTemplateColumns: '1fr 1fr',
                width: '100%', maxWidth: 900, minHeight: 520,
                borderRadius: 18, overflow: 'hidden',
                boxShadow: '0 20px 50px rgba(15,23,42,.16)', background: '#fff',
            }}>
                {/* Brand panel */}
                <div style={{
                    position: 'relative', overflow: 'hidden', padding: 40,
                    display: 'flex', flexDirection: 'column', justifyContent: 'space-between',
                    background: 'linear-gradient(150deg,#0f172a 0%,#1e293b 55%,#1d4ed8 100%)',
                    color: '#fff',
                }}>
                    <div style={{
                        position: 'absolute', top: -70, right: -70, width: 220, height: 220,
                        borderRadius: '9999px', background: 'rgba(255,255,255,.06)',
                    }} />
                    <div style={{ position: 'relative' }}>
                        <div style={{
                            width: 44, height: 44, borderRadius: 12, background: '#2563eb',
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                            fontWeight: 800, fontSize: 18, marginBottom: 20,
                        }}>
                            S
                        </div>
                        <div style={{ fontSize: 24, fontWeight: 700, letterSpacing: '-0.01em' }}>
                            SteelERP
                        </div>
                        <div style={{ fontSize: 13, color: '#cbd5e1', marginTop: 4 }}>
                            Manufacturing &amp; Trading
                        </div>
                    </div>
                    <div style={{ position: 'relative', fontSize: 13, color: '#cbd5e1', lineHeight: 1.7 }}>
                        Purchase · Inventory · Production · Sales
                        <div style={{ marginTop: 8, color: '#94a3b8' }}>
                            Sign in to reach your dashboard.
                        </div>
                    </div>
                </div>

                {/* Form panel */}
                <div style={{ padding: 40, display: 'flex', flexDirection: 'column', justifyContent: 'center' }}>
                    <h1 style={{ fontSize: 22, fontWeight: 700, color: '#0f172a', marginBottom: 4 }}>
                        Welcome back
                    </h1>
                    <p style={{ fontSize: 14, color: '#64748b', marginBottom: 26 }}>
                        Enter your credentials to continue.
                    </p>

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
                            <a
                                href="/forgot-password"
                                style={{ fontSize: 13, color: '#2563eb', textDecoration: 'none' }}
                            >
                                Forgot your password?
                            </a>
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
                </div>
            </div>
        </div>
    );
}
