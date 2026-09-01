import { useState } from 'react';
import useLiveList from '../../../hooks/useLiveList';
import { useToast } from '../../ui/Toast';

export const num = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export const formatDate = (value) => {
    if (!value) return '';
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;

    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
        .replace('Sept', 'Sep');
};

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
