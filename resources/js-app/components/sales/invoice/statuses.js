export const INVOICE_STATUS_LABELS = {
    unpaid: 'Unpaid',
    partial: 'Part Paid',
    paid: 'Paid',
    cancelled: 'Cancelled',
};

export const INVOICE_STATUS_COLOURS = {
    unpaid: '#dc2626',
    partial: '#ca8a04',
    paid: '#16a34a',
    cancelled: '#64748b',
};

/** Blade badged the status; the port rendered it as coloured text. */
export const INVOICE_STATUS_BADGE_CLASS = {
    unpaid: 'badge-red',
    partial: 'badge-yellow',
    paid: 'badge-green',
    cancelled: 'badge-gray',
};

export const badgeClassFor = (status) => INVOICE_STATUS_BADGE_CLASS[status] ?? 'badge-gray';

export const statusLabel = (status) =>
    INVOICE_STATUS_LABELS[status] ?? (status ? status.charAt(0).toUpperCase() + status.slice(1) : '');
