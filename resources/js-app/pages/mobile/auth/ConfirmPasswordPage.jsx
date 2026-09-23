import { FormError, SubmitButton, TextField } from '../../../components/auth/AuthFields';
import { MobileAuthScreen, MobileSpacer } from '../../../components/auth/AuthScreen';
import useAuthForm from '../../../components/auth/useAuthForm';

export default function ConfirmPasswordPage({ navigate = null, post }) {
    const go = navigate ?? ((url) => window.location.assign(url));

    const f = useAuthForm({
        path: '/confirm-password',
        initial: { password: '' },
        post,
        onSuccess: (response) => {
            go(response?.redirect_to || '/app');

            return 'navigating';
        },
    });

    return (
        <MobileAuthScreen
            title="Confirm your password"
            subtitle="This is a secure area. Please confirm your password to continue."
        >
            <FormError message={f.formError} />

            <form
                onSubmit={f.submit}
                noValidate
                style={{ display: 'flex', flexDirection: 'column', flex: 1 }}
            >
                <TextField
                    id="password"
                    label="Password"
                    type="password"
                    autoComplete="current-password"
                    value={f.values.password}
                    error={f.errors.password}
                    disabled={f.submitting}
                    onChange={(value) => f.setField('password', value)}
                />

                <MobileSpacer />

                <SubmitButton submitting={f.submitting} busyLabel="Confirming…" style={{ minHeight: 46 }}>
                    Confirm
                </SubmitButton>
            </form>
        </MobileAuthScreen>
    );
}
