import { useState } from 'react';

const NO_PERMISSION = 'You do not have permission to set unit conversions';

/** 125 -> "125", 0.48 -> "0.48": a factor or a quantity, without trailing zeros. */
const plain = (value) => String(Math.round(Number(value || 0) * 1000) / 1000);

/**
 * The step between counting a line in the supplier's unit and stocking it in
 * ours: "1 BAG = __ KG". It starts from the order's factor or the last one
 * used for this item and unit, and previews what lands on the shelf and at
 * what cost before it is saved. Saving needs `goods-receipts.convert-units`;
 * without it the field is shown disabled with the reason (CLAUDE.md #14).
 *
 * Keyed by the line's saved factor where it is used, so a save — here or on
 * another screen — remounts it on the new figure rather than re-seeding state.
 */
export default function UnitConverter({ line, editable, canConvert, onConvert }) {
    const ours = line.unit_of_measure || 'our unit';
    const theirs = line.supplier_unit;
    const [factor, setFactor] = useState(() => {
        const start = line.unit_factor ?? line.suggested_factor;

        return start ? plain(start) : '';
    });
    const [saving, setSaving] = useState(false);

    const perUnit = parseFloat(factor);
    const valid = perUnit > 0;
    const count = Number(line.supplier_quantity ?? 0);
    const rate = Number(line.supplier_rate ?? 0);
    const changed = valid && perUnit !== Number(line.unit_factor ?? 0);

    // Confirmed, or simply read-only: what was set, by whom.
    if (!editable) {
        return (
            <div style={{ fontSize: '0.8rem', color: '#475569' }}>
                {line.unit_factor
                    ? <>1 {theirs} = <strong>{plain(line.unit_factor)} {ours}</strong>
                        {line.converted_by_name && <span style={{ color: '#94a3b8' }}> · set by {line.converted_by_name}</span>}</>
                    : <span style={{ color: '#b45309' }}>No conversion was set.</span>}
            </div>
        );
    }

    async function save() {
        if (!valid || saving) return;
        setSaving(true);
        await onConvert(line.id, perUnit);
        setSaving(false);
    }

    const blocked = !canConvert ? NO_PERMISSION : (!valid ? `Enter how many ${ours} one ${theirs} holds` : undefined);

    return (
        <div style={{
            display: 'flex', alignItems: 'center', flexWrap: 'wrap', gap: '0.5rem',
            padding: '0.6rem 0.75rem', borderRadius: 8,
            background: line.conversion_pending ? '#fffbeb' : '#f0fdf4',
            border: `1px solid ${line.conversion_pending ? '#fde68a' : '#bbf7d0'}`,
            fontSize: '0.8rem', color: '#78350f',
        }}>
            <span style={{ fontWeight: 700 }}>
                {line.conversion_pending ? 'Conversion needed:' : 'Conversion:'}
            </span>
            <label style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem' }}>
                1 {theirs} =
                <input
                    type="number" min="0" step="any" inputMode="decimal"
                    aria-label={`How many ${ours} one ${theirs} holds, for ${line.item_name}`}
                    disabled={!canConvert || saving}
                    title={canConvert ? undefined : NO_PERMISSION}
                    value={factor}
                    onChange={(e) => setFactor(e.target.value)}
                    onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); save(); } }}
                    style={{
                        width: '6rem', padding: '0.3rem 0.5rem', border: '1.5px solid #fcd34d', borderRadius: 6,
                        fontSize: '0.8rem', background: '#fff',
                        ...(canConvert ? {} : { opacity: 0.5, cursor: 'not-allowed' }),
                    }}
                />
                {ours}
            </label>

            {valid && (
                <span style={{ color: '#92400e' }}>
                    → {plain(count)} {theirs} = <strong>{plain(count * perUnit)} {ours}</strong>
                    {rate > 0 && <> at {(rate / perUnit).toFixed(3)} / {ours}</>}
                </span>
            )}

            {line.conversion_pending && line.suggested_factor && Number(factor) === Number(line.suggested_factor) && (
                <span style={{ fontSize: '0.7rem', color: '#a16207' }}>(suggested)</span>
            )}

            <button
                type="button"
                className="btn-primary btn-sm"
                onClick={save}
                disabled={!!blocked || saving || (!line.conversion_pending && !changed)}
                title={blocked}
                style={{
                    marginLeft: 'auto',
                    ...(blocked ? { opacity: 0.5, cursor: 'not-allowed' } : {}),
                }}
            >
                {saving ? 'Saving…' : (line.conversion_pending ? 'Set conversion' : 'Update')}
            </button>
        </div>
    );
}
