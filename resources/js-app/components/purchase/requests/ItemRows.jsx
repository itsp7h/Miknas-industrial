import { FormSection, TABLE_CELL as CELL, TABLE_FIELD as FIELD, TABLE_HEAD as HEAD } from '../../ui/FormModal';

export function blankRow(date) {
    return { description: '', unit: '', quantity_required: '', purpose_use: '', required_date: date ?? '' };
}

/**
 * Whether the user has actually put something in this row. The required date is
 * ignored: an added row is pre-filled with the previous row's date, so counting
 * it would make every untouched row look filled in.
 */
export function isFilled(row) {
    return ['description', 'unit', 'quantity_required', 'purpose_use']
        .some((field) => String(row[field] ?? '').trim() !== '');
}

/**
 * The Material Details table. Borderless inputs inside bordered cells, exactly
 * as Blade drew it, with the row number recomputed from position rather than
 * stored — which is what its renumber() function was doing by hand.
 *
 * A new row inherits the last row's required date (Blade's create modal did
 * this; its edit modal left the date blank).
 */
export default function ItemRows({ items, units, catalogue = [], accent, today, compact = false, onChange }) {
    // Description and quantity are required, but only on a row being used. In
    // Blade every added row carried `required` unconditionally, so adding a row
    // and leaving it alone made the form refuse to submit with nothing but a
    // browser tooltip to explain why — and its own skip-the-blank-rows code on
    // the server could never be reached.
    const mandatory = (row) => items.length === 1 || isFilled(row);

    function update(index, field, value) {
        onChange(items.map((row, i) => (i === index ? { ...row, [field]: value } : row)));
    }

    /**
     * Typing a description that names a catalogued item brings its unit with
     * it. Anything else leaves the unit alone, so a half-typed word does not
     * keep wiping a unit the user chose by hand.
     */
    function describe(index, value) {
        const match = catalogue.find(
            (item) => item.name.trim().toLowerCase() === value.trim().toLowerCase()
        );

        onChange(items.map((row, i) => (i === index
            ? { ...row, description: value, ...(match?.unit ? { unit: match.unit } : {}) }
            : row)));
    }

    /**
     * Tab completes the description to the first catalogued material it could
     * be, the way a shell completes a path: "ste" becomes "Steel Plate 10mm",
     * and the unit follows as if it had been typed in full.
     *
     * Tab is only swallowed when it actually completed something. With nothing
     * to add — no match, an empty box, or a name already complete — it moves to
     * the next field as Tab always does, so the key is never trapped.
     */
    function completeOnTab(index, event) {
        if (event.key !== 'Tab' || event.shiftKey) return;

        const typed = (items[index]?.description ?? '').trim().toLowerCase();
        if (typed === '') return;

        const names = catalogue.map((item) => item.name).filter(Boolean);
        if (names.some((name) => name.trim().toLowerCase() === typed)) return;

        const match = names.find((name) => name.trim().toLowerCase().startsWith(typed));
        if (!match) return;

        event.preventDefault();
        describe(index, match);
    }

    function add() {
        const last = items[items.length - 1];
        onChange([...items, blankRow(last?.required_date || today)]);
    }

    function remove(index) {
        // Blade refused to remove the last remaining row, so the table can
        // never end up with nothing to submit.
        if (items.length <= 1) return;
        onChange(items.filter((_, i) => i !== index));
    }

    return (
        <FormSection
            accent={accent}
            title="Material Details"
            action={<button type="button" onClick={add} className="btn-primary btn-sm">+ Add Item</button>}
        >

            {/* The table keeps its columns on a phone and scrolls sideways
                instead — a purchase line only makes sense read across. */}
            <div style={{ overflowX: 'auto' }}>
                <table style={{
                    width: '100%', minWidth: compact ? 620 : undefined,
                    borderCollapse: 'collapse', fontSize: '0.8rem',
                }}>
                    <thead>
                        <tr style={{ background: '#fff' }}>
                            <th style={{ ...HEAD, width: '2.5rem' }}>#</th>
                            <th style={HEAD}>Description <span style={{ color: '#f87171' }}>*</span></th>
                            <th style={{ ...HEAD, width: '6rem' }}>Unit</th>
                            <th style={{ ...HEAD, width: '6rem' }}>Qty <span style={{ color: '#f87171' }}>*</span></th>
                            <th style={{ ...HEAD, width: '9rem' }}>Purpose</th>
                            <th style={{ ...HEAD, width: '8rem' }}>Req. Date</th>
                            <th style={{ border: '1px solid #e2e8f0', padding: '0.5rem 0.4rem', width: '2rem' }} />
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((row, index) => (
                            <tr key={index} style={{ background: '#fff' }}>
                                <td style={{
                                    border: '1px solid #e2e8f0', padding: '0.375rem 0.625rem',
                                    textAlign: 'center', color: '#cbd5e1', fontSize: '0.75rem',
                                }}>
                                    {index + 1}
                                </td>
                                <td style={CELL}>
                                    {/* Completes from the item master, and a description
                                        that matches one brings that item's unit with it —
                                        the unit belongs to the item, not to the request.
                                        A material matching nothing is added to the master
                                        when the request is saved. */}
                                    <input
                                        type="text" required={mandatory(row)} style={FIELD} placeholder="Material description"
                                        list="mpr-item-names" autoComplete="off"
                                        aria-label={`Item ${index + 1} description`}
                                        value={row.description ?? ''}
                                        onChange={(e) => describe(index, e.target.value)}
                                        onKeyDown={(e) => completeOnTab(index, e)}
                                    />
                                </td>
                                <td style={CELL}>
                                    <select
                                        style={{ ...FIELD, cursor: 'pointer' }}
                                        aria-label={`Item ${index + 1} unit`}
                                        value={row.unit ?? ''}
                                        onChange={(e) => update(index, 'unit', e.target.value)}
                                    >
                                        <option value="">—</option>
                                        {/* A saved unit outside the standard list stays selectable
                                            rather than being reset to blank on the next save. */}
                                        {row.unit && !units.includes(row.unit) && (
                                            <option value={row.unit}>{row.unit}</option>
                                        )}
                                        {units.map((unit) => <option key={unit} value={unit}>{unit}</option>)}
                                    </select>
                                </td>
                                <td style={CELL}>
                                    <input
                                        type="number" required={mandatory(row)} min="0.01" step="0.01" style={FIELD} placeholder="0"
                                        aria-label={`Item ${index + 1} quantity`}
                                        value={row.quantity_required ?? ''}
                                        onChange={(e) => update(index, 'quantity_required', e.target.value)}
                                    />
                                </td>
                                <td style={CELL}>
                                    <input
                                        type="text" style={FIELD} placeholder="Purpose…"
                                        aria-label={`Item ${index + 1} purpose`}
                                        value={row.purpose_use ?? ''}
                                        onChange={(e) => update(index, 'purpose_use', e.target.value)}
                                    />
                                </td>
                                <td style={CELL}>
                                    <input
                                        type="date" style={FIELD}
                                        aria-label={`Item ${index + 1} required date`}
                                        value={row.required_date ?? ''}
                                        onChange={(e) => update(index, 'required_date', e.target.value)}
                                    />
                                </td>
                                <td style={{ border: '1px solid #e2e8f0', padding: '0.25rem 0.4rem', textAlign: 'center' }}>
                                    <button
                                        type="button" onClick={() => remove(index)}
                                        aria-label={`Remove item ${index + 1}`}
                                        disabled={items.length <= 1}
                                        style={{
                                            color: '#f87171', background: 'none', border: 'none',
                                            cursor: items.length <= 1 ? 'default' : 'pointer',
                                            opacity: items.length <= 1 ? 0.4 : 1,
                                            fontSize: '1.1rem', fontWeight: 700, lineHeight: 1,
                                            padding: '0.125rem 0.25rem', borderRadius: '0.25rem',
                                        }}
                                    >
                                        ×
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {/* One list for every row: the browser completes from it as you
                    type, so "ste" offers Steel Plate without a dropdown of our
                    own to keep in step with the table's layout. */}
                <datalist id="mpr-item-names">
                    {catalogue.map((item) => (
                        <option key={item.id} value={item.name}>
                            {item.unit ? `${item.name} · ${item.unit}` : item.name}
                        </option>
                    ))}
                </datalist>
            </div>
        </FormSection>
    );
}
