import { useCallback, useEffect, useState } from 'react';
import { apiGet } from '../../../api/client';
import { echo } from '../../../echo';
import { useToast } from '../../ui/Toast';

/**
 * Loads one request's detail and keeps it live. `purchase-request.stage-changed`
 * carries only {id, request_number, stage}, so it is treated as an invalidation
 * signal — refetch rather than trying to merge a partial payload into a full
 * detail object.
 */
export default function usePipelineRequest(id) {
    const [request, setRequest] = useState(null);
    const [loading, setLoading] = useState(true);
    const { showToast } = useToast();

    const load = useCallback((quiet = false) => {
        if (!quiet) setLoading(true);

        return apiGet(`/purchase/pipeline/${id}`)
            .then((res) => setRequest(res.data))
            .catch(() => showToast('Failed to load that purchase request.', 'error'))
            .finally(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    useEffect(() => {
        load();
    }, [load]);

    useEffect(() => {
        const channel = echo.private('purchase');
        const handler = (payload) => {
            if (String(payload.id) === String(id)) load(true);
        };
        channel.listen('.purchase-request.stage-changed', handler);

        return () => channel.stopListening('.purchase-request.stage-changed');
    }, [id, load]);

    return { request, loading };
}
