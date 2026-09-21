import { useEffect, useState } from 'react';
import { apiGet } from '../api/client';
import { echo } from '../echo';
import { useToast } from '../components/ui/Toast';

function upsert(prev, payload, mergeKey) {
    const exists = prev.some((item) => item[mergeKey] === payload[mergeKey]);
    return exists
        ? prev.map((item) => (item[mergeKey] === payload[mergeKey] ? payload : item))
        : [...prev, payload];
}

/**
 * Encapsulates the fetch + live-broadcast-subscribe + upsert-by-key pattern shared
 * by every Purchase entity list page (initial fetch with error toast, Echo
 * private-channel subscription, and merging full-payload broadcasts into state).
 */
export default function useLiveList({
    endpoint,
    channel,
    event,
    deleteEvent,
    mergeKey = 'id',
    errorMessage = 'Failed to load data.',
}) {
    const [items, setItems] = useState([]);
    // Endpoints answer with `meta` alongside `data` — option lists, counts.
    // It used to be dropped on the floor, so a caller that needed it had to
    // fetch the same endpoint a second time.
    const [meta, setMeta] = useState({});
    const { showToast } = useToast();

    function refetch() {
        return apiGet(endpoint)
            .then((res) => {
                setItems(res.data);
                setMeta(res.meta ?? {});
            })
            .catch(() => showToast(errorMessage, 'error'));
    }

    useEffect(() => {
        refetch();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [endpoint]);

    useEffect(() => {
        if (!event) return undefined;
        const ch = echo.private(channel);
        const handler = (payload) => setItems((prev) => upsert(prev, payload, mergeKey));
        ch.listen(event, handler);
        return () => ch.stopListening(event);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [channel, event, mergeKey]);

    useEffect(() => {
        if (!deleteEvent) return undefined;
        const ch = echo.private(channel);
        const handler = (payload) => removeItem(payload[mergeKey]);
        ch.listen(deleteEvent, handler);
        return () => ch.stopListening(deleteEvent);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [channel, deleteEvent, mergeKey]);

    function upsertItem(payload) {
        setItems((prev) => upsert(prev, payload, mergeKey));
    }

    function removeItem(id) {
        setItems((prev) => prev.filter((item) => item[mergeKey] !== id));
    }

    return { items, setItems, meta, upsertItem, removeItem, refetch };
}
