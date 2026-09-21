import { money } from '../../../currency';
export const METHOD_LABELS = {
    cash: 'Cash',
    bank_transfer: 'Bank Transfer',
    cheque: 'Cheque',
    other: 'Other',
};

/** The Blade index printed str_replace('_',' ') with a capitalize class. */
export const methodLabel = (method) =>
    METHOD_LABELS[method] ?? String(method ?? '').replace(/_/g, ' ');

// Money goes through the shared helper so the symbol and the currency's own
// precision come from one place — see resources/js-app/currency.js.
export { money };

export const formatDate = (value) => {
    if (!value) return '';
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;

    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
        .replace('Sept', 'Sep');
};
