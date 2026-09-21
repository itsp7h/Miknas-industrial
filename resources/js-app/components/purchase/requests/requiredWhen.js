import { dateLabel } from './UrgencyPicker';

/**
 * `required_date_text` is the urgency picker's answer, and it holds either a
 * preset name — "Urgent", "1 Week" — or a plain `YYYY-MM-DD` when Specific Date
 * was chosen. It is always the urgency, whichever form it took; the request has
 * no date column of its own.
 *
 * This only formats it, so a picked date reads "02 Oct 2026" the way the
 * picker's own pill shows it rather than as raw ISO. The actual Required Date
 * is a separate row, taken from the items.
 */
const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

export function isSpecificDate(value) {
    return typeof value === 'string' && ISO_DATE.test(value.trim());
}

export function formatRequiredWhen(value) {
    if (!value) return null;

    return isSpecificDate(value) ? dateLabel(value.trim()) : value;
}

/** The items' own required date, read as "21 Sep 2026" like every other date here. */
export function formatRequiredDate(value) {
    return value ? dateLabel(String(value).slice(0, 10)) : null;
}
