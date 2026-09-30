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

/** "Tax Invoice", "LPO & Tax Invoice", "LPO, GRN & Tax Invoice". */
const joinLabels = (labels) => (labels.length < 2
    ? labels.join('')
    : `${labels.slice(0, -1).join(', ')} & ${labels[labels.length - 1]}`);

/** "Needs Tax Invoice" while a document is missing, else null. */
export const needsLabel = (grn) => {
    const missing = grn?.missing_documents ?? [];

    return missing.length ? `Needs ${joinLabels(missing)}` : null;
};

/**
 * Why Confirm is disabled, or null when it is not. A receipt saves with a
 * document missing but is only completed — stock received — once the LPO,
 * the GRN and the tax invoice are all on it; the API refuses otherwise.
 */
export const confirmBlockedReason = (grn) => {
    const missing = grn?.missing_documents ?? [];

    return missing.length ? `Upload the ${joinLabels(missing)} to confirm this GRN.` : null;
};
