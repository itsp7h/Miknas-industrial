import { AuthLink, FormError, StatusMessage, SubmitButton, TextField } from '../../../components/auth/AuthFields';
import { DesktopAuthScreen } from '../../../components/auth/AuthScreen';
import useAuthForm from '../../../components/auth/useAuthForm';

/**
 * Ask for a reset link. Whatever the broker reports that is not "sent" —
 * unknown address, asked again inside the throttle window — arrives as a 422
 * on `email` and reads under the field.
 */
export default function ForgotPasswordPage({ post }) {
    const f = useAuthForm({ path: '/forgot-password', initial: { email: '' }, post });

    return (
        <DesktopAuthScreen
            title="Reset your password"
            subtitle="Tell us your email address and we will send you a link to choose a new password."
            blurb="We will email you a link to get back in."
        >
            <StatusMessage message={f.status} />
            <FormError message={f.formError} />

            <form onSubmit={f.submit} noValidate>
                <TextField
                    id="email"
                    label="Email"
                    type="email"
                    autoComplete="username"
                    autoFocus
                    value={f.values.email}
                    error={f.errors.email}
                    disabled={f.submitting}
                    onChange={(value) => f.setField('email', value)}
                />

                <SubmitButton submitting={f.submitting} busyLabel="Sending…">
                    Email password reset link
                </SubmitButton>
            </form>

            <div style={{ marginTop: 18, textAlign: 'center' }}>
                <AuthLink href="/login">Back to sign in</AuthLink>
            </div>
        </DesktopAuthScreen>
    );
}
