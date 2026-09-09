import { useCallback, useEffect, useState } from 'react';
import { apiDelete, apiGet, apiPost, apiPut } from '../../api/client';
import { useToast } from '../ui/Toast';

/** The signed-in user's own profile: details, password, deletion. */
export default function useProfile() {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const { showToast } = useToast();

    const load = useCallback(() => apiGet('/profile')
        .then((response) => setUser(response.data))
        .catch(() => showToast('Failed to load your profile.', 'error'))
        .finally(() => setLoading(false)),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    []);

    useEffect(() => {
        load();
    }, [load]);

    async function saveDetails(values) {
        const response = await apiPut('/profile', values);
        setUser(response.data);
        showToast(response.message, 'success');
    }

    async function savePassword(values) {
        const response = await apiPut('/profile/password', values);
        showToast(response.message, 'success');
    }

    async function resendVerification() {
        const response = await apiPost('/profile/verification-notification');
        showToast(response.message, 'success');
    }

    /**
     * The session dies with the account, so the browser has to leave the shell
     * rather than keep rendering a page it can no longer refresh.
     */
    async function deleteAccount(password) {
        const response = await apiDelete('/profile', { password });
        window.location.assign(response.redirect ?? '/');
    }

    return {
        user, loading,
        saveDetails, savePassword, resendVerification,
        deleteOpen, setDeleteOpen, deleteAccount,
    };
}
