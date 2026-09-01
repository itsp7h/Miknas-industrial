import { useCallback, useEffect, useState } from 'react';
import { apiGet, apiPatch } from '../../../api/client';
import { echo } from '../../../echo';
import { useToast } from '../../ui/Toast';

/** Loads one sales order, keeps it live, and exposes confirm. */
export default function useSalesOrderDetail(id) {
    const [order, setOrder] = useState(null);
    const [loading, setLoading] = useState(true);
    const [confirming, setConfirming] = useState(false);
    const { showToast } = useToast();

    const load = useCallback(() => {
        setLoading(true);

        return apiGet(`/sales/orders/${id}`)
            .then((response) => setOrder(response.data))
            .catch(() => showToast('Failed to load that sales order.', 'error'))
            .finally(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    useEffect(() => {
        load();
    }, [load]);

    useEffect(() => {
        const channel = echo.private('sales');
        // The broadcast payload is the list resource — no lines, no customer card,
        // no delivery notes — so refetch rather than merge a thinner record.
        const handler = (payload) => {
            if (String(payload.id) === String(id)) load();
        };
        channel.listen('.sales-order.saved', handler);

        return () => channel.stopListening('.sales-order.saved');
    }, [id, load]);

    async function confirm() {
        setConfirming(false);
        try {
            await apiPatch(`/sales/orders/${id}/confirm`);
            await load();
            showToast('Order confirmed.', 'success');
        } catch (err) {
            showToast(err.message || 'Failed to confirm the order.', 'error');
        }
    }

    return { order, loading, reload: load, confirming, setConfirming, confirm };
}
