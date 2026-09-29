import { needsLabel } from './grnStyles';

/** "Needs Tax Invoice" beside a receipt's status while its paperwork is incomplete. */
export default function NeedsBadge({ grn, style }) {
    const label = needsLabel(grn);
    if (!label) return null;

    return <span className="badge-red" style={style}>{label}</span>;
}
