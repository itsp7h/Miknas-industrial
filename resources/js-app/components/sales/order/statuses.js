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
