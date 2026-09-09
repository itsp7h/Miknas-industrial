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

export { formatDate, num } from '../formatters';
