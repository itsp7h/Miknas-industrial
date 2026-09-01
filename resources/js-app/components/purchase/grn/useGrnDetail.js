import { useCallback, useEffect, useState } from 'react';
import { apiGet, apiPatch } from '../../../api/client';
import { echo } from '../../../echo';
import { useToast } from '../../ui/Toast';

/** Loads one GRN, keeps it live, and exposes confirm. */
export default function useGrnDetail(id) {
    const [grn, setGrn] = useState(null);
    const [loading, setLoading] = useState(true);
    const [confirming, setConfirming] = useState(false);
    const { showToast } = useToast();

    const load = useCallback((quiet = false) => {
        if (!quiet) setLoading(true);

        return apiGet(`/purchase/grns/${id}`)
            .then((res) => setGrn(res.data))
            .catch(() => showToast('Failed to load that GRN.', 'error'))
            .finally(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    useEffect(() => {
        load();
    }, [load]);

    useEffect(() => {
        const channel = echo.private('purchase');
        const handler = (payload) => {
            if (String(payload.id) === String(id)) setGrn(payload);
        };
        channel.listen('.grn.saved', handler);

        return () => channel.stopListening('.grn.saved');
    }, [id]);

    async function confirm() {
        setConfirming(false);
        try {
            const response = await apiPatch(`/purchase/grns/${id}/confirm`);
            setGrn(response.data);
            showToast('GRN confirmed — stock updated.', 'success');
        } catch (err) {
            showToast(err.message || 'Failed to confirm the GRN.', 'error');
        }
    }

    return { grn, loading, confirming, setConfirming, confirm };
}
