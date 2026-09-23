import { useState } from 'react';
import { apiPost } from '../../api/client';

/**
 * One POST, its 422s and its success message — the shape the forgot-password,
 * reset-password, verify-email and confirm-password screens all have.
 *
 * Login keeps its own hook: it carries remember-me and the dev quick-fill,
 * and it navigates rather than reporting. Everything else is this.
 *
 * `onSuccess` receives the response body, so a caller can navigate (confirm
 * password knows where the user was heading) or stay put and show the status
 * (a reset link was emailed).
 */
export default function useAuthForm({
    path,
    initial = {},
    onSuccess = null,
    post = apiPost,
} = {}) {
    const [values, setValues] = useState(initial);
    const [errors, setErrors] = useState({});
    const [formError, setFormError] = useState('');
    const [status, setStatus] = useState('');
    const [submitting, setSubmitting] = useState(false);

    function setField(name, value) {
        setValues((prev) => ({ ...prev, [name]: value }));
        // Clear the field's error as soon as it is edited: leaving "that
        // address is not registered" under a field being corrected reads as
        // though the new value is wrong too.
        setErrors((prev) => (prev[name] ? { ...prev, [name]: undefined } : prev));
        setFormError('');
    }

    async function submit(event) {
        event?.preventDefault();
        if (submitting) return;

        setSubmitting(true);
        setErrors({});
        setFormError('');
        setStatus('');

        try {
            const response = await post(path, values);
            const handled = onSuccess?.(response, values);

            // A handler that navigates keeps the button busy until the page
            // goes; one that returns nothing hands control back.
            if (handled !== 'navigating') {
                setStatus(response?.message || '');
                setSubmitting(false);
            }
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
                setFormError(err?.message || 'Something went wrong. Please try again.');
            }
            setSubmitting(false);
        }
    }

    return { values, errors, formError, status, submitting, setField, setStatus, submit };
}
