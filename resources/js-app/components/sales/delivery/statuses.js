/**
 * delivery_notes.status is an enum of ('draft', 'dispatched'). Blade badged
 * 'pending' — a value the column cannot hold — so a real draft note fell through
 * to the grey default, and its `status === 'pending'` guard meant the Dispatch
 * button never rendered at all.
 */
export const STATUS_BADGE_CLASS = {
    draft: 'badge-yellow',
    dispatched: 'badge-green',
};

export const badgeClassFor = (status) => STATUS_BADGE_CLASS[status] ?? 'badge-gray';

export const statusLabel = (status) =>
    status ? status.charAt(0).toUpperCase() + status.slice(1) : '';
