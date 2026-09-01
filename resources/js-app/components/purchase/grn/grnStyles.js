export const STATUS_LABELS = { draft: 'Draft', confirmed: 'Confirmed' };

/**
 * The Blade GRN pages hardcoded `badge-green` for every status, so a draft GRN
 * read as green — the same colour as a confirmed one. Draft is amber here, which
 * is the only intentional colour change in this port.
 */
export const STATUS_BADGE_CLASS = { draft: 'badge-yellow', confirmed: 'badge-green' };

export const badgeClassFor = (status) => STATUS_BADGE_CLASS[status] ?? 'badge-yellow';

export const qty = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** `d M Y`, as every Blade purchase page rendered dates. */
export const formatDate = (value) => {
    if (!value) return '—';
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;

    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
        .replace('Sept', 'Sep');
};
