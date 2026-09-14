import { FormSection, TABLE_CELL as CELL, TABLE_FIELD as FIELD, TABLE_HEAD as HEAD } from '../../ui/FormModal';
import { money } from './statuses';

export const blankLine = () => ({ item_id: '', quantity: '', rate: '' });

/**
 * The order's line items, drawn as the MPR's material table is (ItemRows):
 * borderless inputs inside bordered cells, a "+ Add Row" action in the
 * section header, and the row number taken from position. Blade drew the
 * same table on its create page; the React port had replaced it with a stack
 * of bordered flex-wrap cards.
 *
 * The unit column is read-only — it belongs to the item, not to the order —
 * and comes from the form-options payload, which has always carried
 * `unit_of_measure` without anything rendering it.
 */
export default function OrderItemRows({ lines, items, accent, compact, errors, onChange }) {
    const lineError = (index, field) => errors[`items.${index}.${field}`];
    const itemsById = new Map(items.map((item) => [String(item.id), item]));

    const total = lines.reduce(
        (sum, line) => sum + (Number(line.quantity) || 0) * (Number(line.rate) || 0),
        0
    );

    function update(index, field, value) {
        onChange(lines.map((line, i) => (i === index ? { ...line, [field]: value } : line)));
    }

    function add() {
        onChange([...lines, blankLine()]);
    }

    function remove(index) {
        // Always leave one row so the form is never itemless.
        if (lines.length <= 1) return;
        onChange(lines.filter((_, i) => i !== index));
    }

    return (
        <FormSection
            accent={accent}
            title="Order Items"
            action={<button type="button" onClick={add} className="btn-primary btn-sm">+ Add Row</button>}
        >
            {errors.items && (
                <p style={{ marginBottom: '0.5rem', fontSize: '0.75rem', color: '#dc2626' }}>{errors.items}</p>
            )}

            {/* The table keeps its columns on a phone and scrolls sideways
                instead — an order line only makes sense read across. */}
            <div style={{ overflowX: 'auto' }}>
                <table style={{
                    width: '100%', minWidth: compact ? 620 : undefined,
                    borderCollapse: 'collapse', fontSize: '0.8rem',
                }}>
                    <thead>
                        <tr style={{ background: '#fff' }}>
                            <th style={{ ...HEAD, width: '2.5rem' }}>#</th>
                            <th style={HEAD}>Item <span style={{ color: '#f87171' }}>*</span></th>
                            <th style={{ ...HEAD, width: '5rem' }}>Unit</th>
                            <th style={{ ...HEAD, width: '6rem' }}>Qty <span style={{ color: '#f87171' }}>*</span></th>
                            <th style={{ ...HEAD, width: '7rem' }}>Rate <span style={{ color: '#f87171' }}>*</span></th>
                            <th style={{ ...HEAD, width: '8rem', textAlign: 'right' }}>Total</th>
                            <th style={{ border: '1px solid #e2e8f0', padding: '0.5rem 0.4rem', width: '2rem' }} />
                        </tr>
                    </thead>
                    <tbody>
                        {lines.map((line, index) => {
                            const item = itemsById.get(String(line.item_id));
                            const rowTotal = (Number(line.quantity) || 0) * (Number(line.rate) || 0);

                            return (
                                <tr key={index} style={{ background: '#fff' }}>
                                    <td style={{ ...CELL, color: '#94a3b8', textAlign: 'center' }}>{index + 1}</td>
                                    <td style={CELL}>
                                        <select
                                            aria-label={`Item for row ${index + 1}`}
                                            style={{ ...FIELD, color: lineError(index, 'item_id') ? '#dc2626' : undefined }}
                                            value={line.item_id}
                                            onChange={(e) => update(index, 'item_id', e.target.value)}
                                        >
                                            <option value="">— Select Item —</option>
                                            {items.map((option) => (
                                                <option key={option.id} value={option.id}>
                                                    {option.item_code} - {option.item_name}
                                                </option>
                                            ))}
                                        </select>
                                    </td>
                                    <td style={{ ...CELL, color: '#94a3b8' }}>{item?.unit_of_measure || '—'}</td>
                                    <td style={CELL}>
                                        <input
                                            type="number" step="0.01" min="0" placeholder="0"
                                            aria-label={`Quantity for row ${index + 1}`}
                                            style={FIELD}
                                            value={line.quantity}
                                            onChange={(e) => update(index, 'quantity', e.target.value)}
                                        />
                                    </td>
                                    <td style={CELL}>
                                        <input
                                            type="number" step="0.01" min="0" placeholder="0.00"
                                            aria-label={`Rate for row ${index + 1}`}
                                            style={FIELD}
                                            value={line.rate}
                                            onChange={(e) => update(index, 'rate', e.target.value)}
                                        />
                                    </td>
                                    <td style={{ ...CELL, textAlign: 'right', fontWeight: 600 }}>
                                        {rowTotal > 0 ? money(rowTotal) : '—'}
                                    </td>
                                    <td style={{ ...CELL, textAlign: 'center' }}>
                                        {lines.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() => remove(index)}
                                                aria-label={`Remove row ${index + 1}`}
                                                style={{
                                                    background: 'none', border: 0, cursor: 'pointer',
                                                    color: '#f87171', fontSize: '1.1rem', lineHeight: 1,
                                                }}
                                            >
                                                ×
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <div style={{
                display: 'flex', justifyContent: 'flex-end', alignItems: 'baseline',
                gap: '0.75rem', marginTop: '0.875rem',
            }}>
                <span style={{ fontSize: '0.75rem', color: '#64748b' }}>Grand Total</span>
                <span style={{ fontSize: '1.05rem', fontWeight: 700, color: '#0f172a' }}>{money(total)}</span>
            </div>
        </FormSection>
    );
}
