import { qty } from '../../currency';
/**
 * Shared by the production pages so their tables read the same as Blade's did.
 * Every figure on them is a quantity produced or issued, never an amount.
 */
export { qty as num };

export const formatDate = (value) => {
    if (!value) return '';
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;

    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
        .replace('Sept', 'Sep');
};
