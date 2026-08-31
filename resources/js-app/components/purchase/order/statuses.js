// Mirrors the badge palette the Blade purchase-orders index used
// (badge-gray / badge-blue / badge-green / badge-red).
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
