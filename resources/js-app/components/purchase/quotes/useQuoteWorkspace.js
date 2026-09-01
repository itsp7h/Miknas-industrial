import { useCallback, useEffect, useState } from 'react';
import { apiGet, apiPost } from '../../../api/client';
import { useToast } from '../../ui/Toast';

/**
 * The workspace's data and the two award writes. Both answer with the whole
 * workspace, so one response redraws every card and the totals — the Blade page
 * patched a single card's DOM and recomputed the footer by hand.
 */
export default function useQuoteWorkspace(requestId) {
    const [workspace, setWorkspace] = useState(null);
    const [loading, setLoading] = useState(true);
    const [tab, setTab] = useState('comparison');
    const [awarding, setAwarding] = useState(null);
    const [detail, setDetail] = useState(null);
    const { showToast } = useToast();

    const load = useCallback(() => {
        setLoading(true);

        return apiGet(`/purchase/requests/${requestId}/quotes`)
            .then((response) => setWorkspace(response.data))
            .catch((err) => showToast(err?.message || 'Failed to load the quotes.', 'error'))
            .finally(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [requestId]);

    useEffect(() => {
        load();
    }, [load]);

    async function award(lineId, reason) {
        const response = await apiPost(
            `/purchase/requests/${requestId}/quotes/items/${lineId}/award`,
            { award_reason: reason }
        );
        setWorkspace(response.data);
        showToast(response.message, 'success');
    }

    async function unaward(lineId) {
        const response = await apiPost(`/purchase/requests/${requestId}/quotes/items/${lineId}/unaward`);
        setWorkspace(response.data);
        showToast(response.message, 'success');
    }

    return {
        workspace, loading, tab, setTab,
        awarding, setAwarding, award,
        detail, setDetail, unaward,
    };
}
