import { money } from '../../../currency';
/** Kept as a name the quotes and pipeline screens already use. */
export const bd = money;

/** The Blade page trimmed trailing zeros off the VAT rate: 10.00 → 10. */
export const rate = (value) => String(Number(value ?? 0)).replace(/\.0+$/, '');
