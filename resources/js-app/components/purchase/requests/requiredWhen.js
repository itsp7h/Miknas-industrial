import { dateLabel } from './UrgencyPicker';

/**
 * `required_date_text` is one column holding two different answers.
 *
 * The urgency picker writes either a preset name — "Urgent", "1 Week" — or,
 * when Specific Date is chosen, a plain `YYYY-MM-DD` string. So a single fixed
 * label is wrong half the time: "Required Urgency: 2026-10-02" reads as badly
 * as "Required Date: Urgent" did.
 *
 * The value says which it is, so the row labels itself and formats a real date
 * the way the picker's own pill does.
 */
const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

export function isSpecificDate(value) {
    return typeof value === 'string' && ISO_DATE.test(value.trim());
}

export function requiredWhen(value) {
    if (!value) return null;

    return isSpecificDate(value)
        ? { label: 'Required Date', value: dateLabel(value.trim()) }
        : { label: 'Required Urgency', value };
}
