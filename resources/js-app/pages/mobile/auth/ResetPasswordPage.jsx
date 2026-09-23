import { FormError, SubmitButton } from '../../../components/auth/AuthFields';
import { MobileAuthScreen, MobileSpacer } from '../../../components/auth/AuthScreen';
import { ResetDone, ResetPasswordFields } from '../../../components/auth/ResetPasswordFields';
import useAuthForm from '../../../components/auth/useAuthForm';

export default function ResetPasswordPage({ token = '', email = '', post }) {
    const f = useAuthForm({
        path: '/reset-password',
        initial: { token, email, password: '', password_confirmation: '' },
        post,
    });

    return (
        <MobileAuthScreen
            title="Choose a new password"
            subtitle="Set a new password for your account."
        >
            {f.status ? (
                <ResetDone message={f.status} compact />
            ) : (
                <>
                    <FormError message={f.formError} />

                    <form
                        onSubmit={f.submit}
                        noValidate
                        style={{ display: 'flex', flexDirection: 'column', flex: 1 }}
                    >
                        <ResetPasswordFields
                            values={f.values}
                            errors={f.errors}
                            setField={f.setField}
                            submitting={f.submitting}
                            compact
                        />

                        <MobileSpacer />

                        <SubmitButton submitting={f.submitting} busyLabel="Saving…" style={{ minHeight: 46 }}>
                            Reset password
                        </SubmitButton>
                    </form>
                </>
            )}
        </MobileAuthScreen>
    );
}
