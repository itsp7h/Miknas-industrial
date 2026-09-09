/** The Blade table badged type as a green IN / red OUT pill. */
export const typeBadgeClass = (type) =>
    String(type ?? '').toLowerCase() === 'in' ? 'badge-green' : 'badge-red';

export const typeLabel = (type) =>
    String(type ?? '').toLowerCase() === 'in' ? 'IN' : 'OUT';

export const num = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** `d M Y`, as the Blade table rendered created_at. */
export const formatDate = (value) => {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
        .replace('Sept', 'Sep');
};
