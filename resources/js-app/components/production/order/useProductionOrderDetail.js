import { useCallback, useEffect, useState } from 'react';
import { apiGet, apiPatch } from '../../../api/client';
import { echo } from '../../../echo';
import { useToast } from '../../ui/Toast';

/** Loads one production order, keeps it live, and exposes start/complete. */
export default function useProductionOrderDetail(id) {
    const [order, setOrder] = useState(null);
    const [loading, setLoading] = useState(true);
    const [starting, setStarting] = useState(false);
    const [completing, setCompleting] = useState(false);
    const { showToast } = useToast();

    const load = useCallback(() => {
        setLoading(true);

        return apiGet(`/production/orders/${id}`)
            .then((res) => setOrder(res.data))
            .catch(() => showToast('Failed to load that production order.', 'error'))
            .finally(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    useEffect(() => {
        load();
    }, [load]);

    useEffect(() => {
        const channel = echo.private('production');
        // The broadcast payload is the list resource, which carries none of the
        // BOM/issue/output sections — so refetch rather than merge a thinner
        // record over the full one.
        const handler = (payload) => {
            if (String(payload.id) === String(id)) load();
        };
        channel.listen('.production-order.saved', handler);

        return () => channel.stopListening('.production-order.saved');
    }, [id, load]);

    async function transition(action, message) {
        setStarting(false);
        setCompleting(false);
        try {
            await apiPatch(`/production/orders/${id}/${action}`);
            await load();
            showToast(message, 'success');
        } catch (err) {
            showToast(err.message || `Failed to ${action} the order.`, 'error');
        }
    }

    return {
        order, loading,
        starting, setStarting, start: () => transition('start', 'Production started.'),
        completing, setCompleting, complete: () => transition('complete', 'Production order completed.'),
    };
}
