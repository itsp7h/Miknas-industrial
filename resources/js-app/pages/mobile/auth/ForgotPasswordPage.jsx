import { AuthLink, FormError, StatusMessage, SubmitButton, TextField } from '../../../components/auth/AuthFields';
import { MobileAuthScreen, MobileSpacer } from '../../../components/auth/AuthScreen';
import useAuthForm from '../../../components/auth/useAuthForm';

/** Mobile: one field, the action near the thumb (CLAUDE.md #12). */
export default function ForgotPasswordPage({ post }) {
    const f = useAuthForm({ path: '/forgot-password', initial: { email: '' }, post });

    return (
        <MobileAuthScreen
            title="Reset your password"
            subtitle="We will email you a link to choose a new password."
        >
            <StatusMessage message={f.status} />
            <FormError message={f.formError} />

            <form
                onSubmit={f.submit}
                noValidate
                style={{ display: 'flex', flexDirection: 'column', flex: 1 }}
            >
                <TextField
                    id="email"
                    label="Email"
                    type="email"
                    autoComplete="username"
                    value={f.values.email}
                    error={f.errors.email}
                    disabled={f.submitting}
                    onChange={(value) => f.setField('email', value)}
                />

                <MobileSpacer />

                <SubmitButton submitting={f.submitting} busyLabel="Sending…" style={{ minHeight: 46 }}>
                    Email password reset link
                </SubmitButton>

                <AuthLink
                    href="/login"
                    style={{ display: 'block', textAlign: 'center', marginTop: 14, fontSize: 13.5 }}
                >
                    Back to sign in
                </AuthLink>
            </form>
        </MobileAuthScreen>
    );
}
