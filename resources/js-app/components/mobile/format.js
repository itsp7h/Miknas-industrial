// Dates as the mobile design writes them: "07 Oct" in a list, "07 Oct 2026"
// on a detail. Built by hand, not toLocaleDateString — en-GB writes
// September as "Sept" (see layouts/TopBar.jsx).

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

function parse(value) {
    if (!value) return null;
    // A bare YYYY-MM-DD is a calendar date, not midnight UTC: read it as local
    // so a Bahrain user does not see the day before.
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
    const d = m ? new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])) : new Date(value);

    return Number.isNaN(d.getTime()) ? null : d;
}

export function shortDate(value) {
    const d = parse(value);

    return d ? `${String(d.getDate()).padStart(2, '0')} ${MONTHS[d.getMonth()]}` : '—';
}

export function longDate(value) {
    const d = parse(value);

    return d ? `${shortDate(value)} ${d.getFullYear()}` : '—';
}

export function dateTime(value) {
    const d = parse(value);
    if (!d) return '—';

    return `${shortDate(value)}, ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}
