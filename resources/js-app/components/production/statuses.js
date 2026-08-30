export const PO_STATUS_LABELS = {
    planned: 'Planned',
    in_progress: 'In Progress',
    completed: 'Completed',
    cancelled: 'Cancelled',
};

export const PO_STATUS_COLOURS = {
    planned: '#64748b',
    in_progress: '#2563eb',
    completed: '#16a34a',
    cancelled: '#dc2626',
};

export const qty = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { maximumFractionDigits: 2 });
