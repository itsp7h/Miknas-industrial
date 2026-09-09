import { useCallback, useEffect, useState } from 'react';
import { apiGet } from '../../../api/client';
import { useToast } from '../../ui/Toast';

/**
 * Reports are read-only snapshots: fetch, expose rows + meta, and allow a
 * re-fetch when server-side filters change. No broadcast subscription — a
 * report is a point-in-time view, not a live list.
 */
export default function useReport(endpoint, { errorMessage = 'Failed to load report.' } = {}) {
    const [rows, setRows] = useState([]);
    const [meta, setMeta] = useState({});
    const [loading, setLoading] = useState(true);
    const { showToast } = useToast();

    const load = useCallback(
        (query = '') => {
            setLoading(true);
            return apiGet(`${endpoint}${query}`)
                .then((response) => {
                    setRows(response.data ?? []);
                    setMeta(response.meta ?? {});
                })
                .catch(() => showToast(errorMessage, 'error'))
                .finally(() => setLoading(false));
        },
        // showToast is stable from the provider; endpoint drives the identity.
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [endpoint]
    );

    useEffect(() => {
        load();
    }, [load]);

    return { rows, meta, loading, reload: load };
}
