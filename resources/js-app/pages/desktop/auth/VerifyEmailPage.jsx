import { FormError, StatusMessage } from '../../../components/auth/AuthFields';
import { DesktopAuthScreen } from '../../../components/auth/AuthScreen';
import useVerifyEmail from '../../../components/auth/useVerifyEmail';

/**
 * The wall in front of an unverified account. Not a form in the usual sense:
 * the only inputs are "send it again" and "sign out", so both are buttons and
 * the address is shown so the visitor can tell whether it is the right one.
 */
export default function VerifyEmailPage({ email = '', navigate = null, post }) {
    const f = useVerifyEmail({ navigate, post });

    return (
        <DesktopAuthScreen
            title="Verify your email"
            subtitle="Before you can get started, please confirm the address we sent a link to."
            blurb="Check your inbox to finish setting up."
        >
            <StatusMessage message={f.status} />
            <FormError message={f.formError} />

            <p style={{ fontSize: 14, color: '#334155', lineHeight: 1.7, margin: '0 0 8px' }}>
                We emailed a verification link
                {email ? <> to <strong>{email}</strong></> : null}. Click it and you are
                done. If it has not arrived, we will gladly send another.
            </p>

            <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginTop: 22 }}>
                <button
                    type="button"
                    className="btn btn-primary"
                    disabled={f.sending}
                    onClick={f.resend}
                    style={{ flex: 1, justifyContent: 'center' }}
                >
                    {f.sending ? 'Sending…' : 'Resend verification email'}
                </button>
                <button
                    type="button"
                    disabled={f.signingOut}
                    onClick={f.signOut}
                    style={{
                        background: 'none', border: 'none', padding: '8px 4px',
                        fontSize: 13, color: '#64748b', textDecoration: 'underline',
                        cursor: 'pointer',
                    }}
                >
                    {f.signingOut ? 'Signing out…' : 'Log out'}
                </button>
            </div>
        </DesktopAuthScreen>
    );
}
