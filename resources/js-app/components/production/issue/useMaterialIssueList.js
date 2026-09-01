import { useState } from 'react';
import useLiveList from '../../../hooks/useLiveList';
import { useToast } from '../../ui/Toast';

// Re-exported so the pages can pull the row formatters from the same place as
// the hook they already import.
export { formatDate, num } from '../formatters';

/** Live list plus client-side search, shared by both viewports. */
export default function useMaterialIssueList() {
    const { items: issues, upsertItem } = useLiveList({
        endpoint: '/production/material-issues',
        channel: 'production',
        event: '.material-issue.recorded',
        mergeKey: 'id',
        errorMessage: 'Failed to load material issues.',
    });
    const [query, setQuery] = useState('');
    const { showToast } = useToast();

    function handleSaved(issue) {
        upsertItem(issue);
        showToast('Material issued — stock updated.', 'success');
    }

    const q = query.trim().toLowerCase();
    const filtered = q
        ? issues.filter((issue) => [
            issue.issue_number, issue.production_order_number,
            issue.item_name, issue.warehouse_name, issue.notes,
        ].some((field) => String(field ?? '').toLowerCase().includes(q)))
        : issues;

    return { issues, filtered, query, setQuery, handleSaved };
}
