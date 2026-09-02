import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { apiDelete, apiGet } from '../../../api/client';
import { useToast } from '../../ui/Toast';

/**
 * One request's MPR sheet. Deleting takes you back to the board, since the
 * record you were looking at no longer exists.
 */
export default function useRequestSheet(id) {
    const [request, setRequest] = useState(null);
    const [loading, setLoading] = useState(true);
    const [deleting, setDeleting] = useState(false);
    const { showToast } = useToast();
    const navigate = useNavigate();

    const load = useCallback(() => {
        setLoading(true);

        return apiGet(`/purchase/requests/${id}`)
            .then((response) => setRequest(response.data))
            .catch(() => showToast('Failed to load that purchase request.', 'error'))
            .finally(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    useEffect(() => { load(); }, [load]);

    async function destroy() {
        try {
            const response = await apiDelete(`/purchase/requests/${id}`);
            showToast(response.message, 'success');
            navigate('/app/purchase/pipeline');
        } catch (rejection) {
            showToast(rejection?.message || 'Could not delete that request.', 'error');
        } finally {
            setDeleting(false);
        }
    }

    return { request, loading, deleting, setDeleting, destroy, applyUpdate: setRequest, reload: load };
}
