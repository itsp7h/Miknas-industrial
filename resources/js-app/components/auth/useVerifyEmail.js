import { useState } from 'react';
import { apiPost } from '../../api/client';

/**
 * The verify-email screen's two actions: send another link, or sign out and
 * come back later. Shared by both viewports so they cannot disagree about
 * which endpoint does what.
 *
 * The resend reuses the profile endpoint rather than adding a second one —
 * it is the same act (`sendEmailVerificationNotification` on the current
 * user), and this screen's visitor is authenticated, just not yet verified.
 */
export default function useVerifyEmail({ post = apiPost, navigate = null } = {}) {
    const go = navigate ?? ((url) => window.location.assign(url));
    const [status, setStatus] = useState('');
    const [formError, setFormError] = useState('');
    const [sending, setSending] = useState(false);
    const [signingOut, setSigningOut] = useState(false);

    async function resend(event) {
        event?.preventDefault();
        if (sending) return;

        setSending(true);
        setStatus('');
        setFormError('');

        try {
            const response = await post('/profile/verification-notification', {});
            setStatus(response?.message || 'A new verification link has been sent.');
        } catch (err) {
            setFormError(err?.message || 'The link could not be sent. Please try again.');
        }

        setSending(false);
    }

    async function signOut(event) {
        event?.preventDefault();
        if (signingOut) return;

        setSigningOut(true);

        try {
            await post('/logout', {});
            go('/');
        } catch (err) {
            setFormError(err?.message || 'Sign out failed. Please try again.');
            setSigningOut(false);
        }
    }

    return { status, formError, sending, signingOut, resend, signOut };
}
