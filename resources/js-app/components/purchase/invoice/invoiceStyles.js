import { money } from '../../../currency';
export const STATUS_LABELS = { unpaid: 'Unpaid', partial: 'Partial', paid: 'Paid' };

// The Blade index's badge mapping.
export const STATUS_BADGE_CLASS = {
    unpaid: 'badge-red',
    partial: 'badge-yellow',
    paid: 'badge-green',
};

export const badgeClassFor = (status) => STATUS_BADGE_CLASS[status] ?? 'badge-gray';

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
