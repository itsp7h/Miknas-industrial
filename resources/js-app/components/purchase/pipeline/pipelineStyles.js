// Colour maps copied from resources/views/purchase/pipeline/show.blade.php.

/** RFQ invitation status pill. */
export const INVITATION_STATUS = {
    pending: { bg: '#f1f5f9', fg: '#64748b', label: 'Pending' },
    sent: { bg: '#dbeafe', fg: '#1d4ed8', label: 'Sent' },
    opened: { bg: '#e0e7ff', fg: '#3730a3', label: 'Opened' },
    submitted: { bg: '#dcfce7', fg: '#15803d', label: 'Submitted' },
    declined: { bg: '#fee2e2', fg: '#991b1b', label: 'Declined' },
};

/** Linked-LPO status pill. */
export const PO_STATUS = {
    draft: { bg: '#f1f5f9', fg: '#64748b', label: 'Draft' },
    sent: { bg: '#dbeafe', fg: '#1d4ed8', label: 'Sent' },
    received: { bg: '#dcfce7', fg: '#15803d', label: 'Received' },
    cancelled: { bg: '#fee2e2', fg: '#991b1b', label: 'Cancelled' },
};

export const CARD = {
    background: '#fff', borderRadius: 14,
    boxShadow: '0 2px 10px rgba(0,0,0,.05)', padding: 20,
};

export const CARD_TITLE = {
    fontSize: 13, fontWeight: 700, color: '#0f172a', margin: '0 0 14px',
};

export const PILL = {
    padding: '2px 8px', borderRadius: 12, fontWeight: 700, fontSize: 10,
};

/** The Blade page printed quote and LPO money to three decimals, prefixed BD. */
export const bd = (value) =>
    `BD ${Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 3, maximumFractionDigits: 3 })}`;

export const formatDate = (value) => {
    if (!value) return '—';
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;

    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
        .replace('Sept', 'Sep');
};
