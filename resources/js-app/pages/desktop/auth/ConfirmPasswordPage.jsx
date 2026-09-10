import { FormError, SubmitButton, TextField } from '../../../components/auth/AuthFields';
import { DesktopAuthScreen } from '../../../components/auth/AuthScreen';
import useAuthForm from '../../../components/auth/useAuthForm';

/**
 * The password re-prompt in front of a sensitive area. On success the server
 * says where the user was heading; the browser navigates rather than
 * router-pushing, because this screen is outside the SPA and the destination
 * is inside it.
 */
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
        <DesktopAuthScreen
            title="Confirm your password"
            subtitle="This is a secure area. Please confirm your password before continuing."
            blurb="A quick check before you continue."
        >
            <FormError message={f.formError} />

            <form onSubmit={f.submit} noValidate>
                <TextField
                    id="password"
                    label="Password"
                    type="password"
                    autoComplete="current-password"
                    autoFocus
                    value={f.values.password}
                    error={f.errors.password}
                    disabled={f.submitting}
                    onChange={(value) => f.setField('password', value)}
                />

                <SubmitButton submitting={f.submitting} busyLabel="Confirming…">
                    Confirm
                </SubmitButton>
            </form>
        </DesktopAuthScreen>
    );
}
