export const METHOD_LABELS = {
    cash: 'Cash',
    bank_transfer: 'Bank Transfer',
    cheque: 'Cheque',
    other: 'Other',
};

/** The Blade index printed str_replace('_',' ') with a capitalize class. */
export const methodLabel = (method) =>
    METHOD_LABELS[method] ?? String(method ?? '').replace(/_/g, ' ');

export const money = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export const formatDate = (value) => {
    if (!value) return '';
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;

    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
        .replace('Sept', 'Sep');
};
