// The Blade purchase-orders index badged status with these shared classes from
// resources/css/app.css, which the React shell also loads.
export const STATUS_BADGE_CLASS = {
    draft: 'badge-gray',
    sent: 'badge-blue',
    received: 'badge-green',
    cancelled: 'badge-red',
};

export const badgeClassFor = (status) => STATUS_BADGE_CLASS[status] ?? 'badge-gray';

export const STATUS_LABELS = {
    draft: 'Draft',
    sent: 'Sent',
    received: 'Received',
    cancelled: 'Cancelled',
};

export const STATUS_COLOURS = {
    draft: '#64748b',
    sent: '#1d4ed8',
    received: '#065f46',
    cancelled: '#991b1b',
};

export const STATUS_BACKGROUNDS = {
    draft: '#f1f5f9',
    sent: '#dbeafe',
    received: '#d1fae5',
    cancelled: '#fee2e2',
};

export const money = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** The Blade pages rendered dates as `d M Y`; keep that rather than raw ISO. */
export const formatDate = (value) => {
    if (!value) return '—';
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};
