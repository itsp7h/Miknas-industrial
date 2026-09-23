import { FormError, StatusMessage } from '../../../components/auth/AuthFields';
import { MobileAuthScreen, MobileSpacer } from '../../../components/auth/AuthScreen';
import useVerifyEmail from '../../../components/auth/useVerifyEmail';

export default function VerifyEmailPage({ email = '', navigate = null, post }) {
    const f = useVerifyEmail({ navigate, post });

    return (
        <MobileAuthScreen
            title="Verify your email"
            subtitle="One link stands between you and the app."
        >
            <StatusMessage message={f.status} />
            <FormError message={f.formError} />

            <p style={{ fontSize: 14, color: '#334155', lineHeight: 1.7, margin: 0 }}>
                We emailed a verification link
                {email ? <> to <strong>{email}</strong></> : null}. Click it and you are
                done. If it has not arrived, we will gladly send another.
            </p>

            <MobileSpacer />

            <button
                type="button"
                className="btn btn-primary"
                disabled={f.sending}
                onClick={f.resend}
                style={{ width: '100%', justifyContent: 'center', minHeight: 46 }}
            >
                {f.sending ? 'Sending…' : 'Resend verification email'}
            </button>

            <button
                type="button"
                disabled={f.signingOut}
                onClick={f.signOut}
                style={{
                    background: 'none', border: 'none', marginTop: 14, padding: 8,
                    fontSize: 13.5, color: '#64748b', textDecoration: 'underline',
                    cursor: 'pointer', width: '100%',
                }}
            >
                {f.signingOut ? 'Signing out…' : 'Log out'}
            </button>
        </MobileAuthScreen>
    );
}
