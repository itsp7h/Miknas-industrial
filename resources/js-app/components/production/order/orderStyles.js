/**
 * The production_orders.status enum is ['planned','in_progress','completed',
 * 'cancelled'].
 *
 * The Blade pages matched on 'pending', which is not in that enum — so the
 * first status fell through to a grey badge instead of amber, and the Start
 * button's `$order->status === 'pending'` guard was never true, making Start
 * unreachable from the UI entirely.
 */
export const STATUS_BADGE_CLASS = {
    planned: 'badge-yellow',
    in_progress: 'badge-blue',
    completed: 'badge-green',
    cancelled: 'badge-red',
};

export const badgeClassFor = (status) => STATUS_BADGE_CLASS[status] ?? 'badge-gray';

/** Blade rendered ucwords(str_replace('_', ' ', status)). */
export const statusLabel = (status) =>
    String(status ?? '')
        .split('_')
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');

export const num = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export const formatDate = (value) => {
    if (!value) return '';
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;

    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
        .replace('Sept', 'Sep');
};
