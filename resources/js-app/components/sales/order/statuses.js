export const STATUS_LABELS = {
    draft: 'Draft',
    confirmed: 'Confirmed',
    dispatched: 'Dispatched',
    invoiced: 'Invoiced',
    cancelled: 'Cancelled',
};

export const STATUS_COLOURS = {
    draft: '#64748b',
    confirmed: '#2563eb',
    dispatched: '#7c3aed',
    invoiced: '#16a34a',
    cancelled: '#dc2626',
};

export const money = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/**
 * Blade badged the status; the port had rendered it as coloured text instead.
 * Violet for dispatched is the one badge colour used nowhere else in the app.
 */
export const STATUS_BADGE_CLASS = {
    draft: 'badge-gray',
    confirmed: 'badge-blue',
    dispatched: 'badge-violet',
    invoiced: 'badge-green',
    cancelled: 'badge-red',
};

export const badgeClassFor = (status) => STATUS_BADGE_CLASS[status] ?? 'badge-gray';

export const formatDate = (value) => {
    if (!value) return '';
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;

    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
        .replace('Sept', 'Sep');
};

/** Blade printed ucfirst($status). */
export const statusLabel = (status) =>
    STATUS_LABELS[status] ?? (status ? status.charAt(0).toUpperCase() + status.slice(1) : '');
