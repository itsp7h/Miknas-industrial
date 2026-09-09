import { useEffect, useState } from 'react';
import { apiGet } from '../../api/client';
import { echo } from '../../echo';
import { useToast } from '../ui/Toast';

/**
 * Fetches the KPI figures and subscribes to the user's private channel for
 * live pings. Shared so the desktop and mobile dashboards cannot diverge on
 * data-loading behaviour.
 */
export default function useDashboardSummary(currentUserId) {
    const [summary, setSummary] = useState(null);
    const { showToast } = useToast();

    useEffect(() => {
        apiGet('/dashboard/summary')
            .then(setSummary)
            .catch(() => showToast('Failed to load dashboard figures.', 'error'));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (!currentUserId) return undefined;

        const name = `App.Models.User.${currentUserId}`;
        const channel = echo.private(name);
        channel.listen('.dashboard.pinged', (event) => showToast(event.message, 'info'));

        return () => echo.leave(name);
    }, [currentUserId, showToast]);

    return summary;
}
