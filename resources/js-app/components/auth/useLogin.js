import { useState } from 'react';
import { apiPost } from '../../api/client';

/**
 * Login state and submission, shared by the desktop and mobile pages so the
 * two differ only in layout.
 *
 * Posts to /api/v1/login, which runs Breeze's LoginRequest — so validation,
 * the 5-attempt rate limit and remember-me behave exactly as they did on the
 * Blade form. A rate-limit lockout arrives as a 422 on the `email` field, the
 * same shape as a wrong password, and reads as its own sentence.
 *
 * On success the browser navigates rather than router-pushes: the session
 * cookie has just been regenerated, and a full load boots the SPA shell with
 * the new identity instead of leaving a guest-mounted tree in place.
 */
export default function useLogin({ redirectTo = '/app', navigate = null } = {}) {
    const [values, setValues] = useState({ email: '', password: '', remember: false });
    const [errors, setErrors] = useState({});
    const [formError, setFormError] = useState('');
    const [submitting, setSubmitting] = useState(false);

    function setField(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
        // Clear the field's error as soon as it is edited: leaving "these
        // credentials do not match" under a field the user is fixing reads as
        // though the new value is wrong too.
        setErrors((prev) => (prev[name] ? { ...prev, [name]: undefined } : prev));
        setFormError('');
    }

    function fillDemo(email, password) {
        setValues((prev) => ({ ...prev, email, password }));
        setErrors({});
        setFormError('');
    }

    async function submit(event) {
        event?.preventDefault();
        if (submitting) return;

        setSubmitting(true);
        setErrors({});
        setFormError('');

        try {
            await apiPost('/login', values);
            (navigate ?? ((url) => window.location.assign(url)))(redirectTo);
        } catch (err) {
            const fieldErrors = err?.errors ?? {};
            setErrors(
                Object.fromEntries(
                    Object.entries(fieldErrors).map(([key, list]) => [
                        key,
                        Array.isArray(list) ? list[0] : String(list),
                    ])
                )
            );
            if (!Object.keys(fieldErrors).length) {
                setFormError(err?.message || 'Sign in failed. Please try again.');
            }
            setSubmitting(false);
        }
    }

    return { values, errors, formError, submitting, setField, fillDemo, submit };
}
