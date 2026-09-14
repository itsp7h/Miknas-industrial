import { FormSection, TABLE_CELL as CELL, TABLE_FIELD as FIELD, TABLE_HEAD as HEAD } from '../../ui/FormModal';
import { qty } from './grnStyles';

const TYPES = ['inventory', 'consumable'];

/**
 * The lines being received, drawn as the MPR and purchase order tables are.
 * Rows are not added or removed here — they come from the chosen purchase
 * order — so there is no "+ Add Row"; what is editable is the quantity
 * actually received and whether the line lands in stock or is consumed.
 *
 * PO Qty and Unit Cost are shown but read-only: they belong to the order.
 * `unit_cost` still rides along in the payload, as the API accepts it and
 * the stock movement is costed from it.
 */
export default function GrnItemRows({ lines, accent, compact, errors, hasOrder, onChange }) {
    const lineError = (index, field) => errors[`items.${index}.${field}`];

    function update(index, field, value) {
        onChange(lines.map((line, i) => (i === index ? { ...line, [field]: value } : line)));
    }

    return (
        <FormSection accent={accent} title="Items Received">
            {errors.items && (
                <p style={{ marginBottom: '0.5rem', fontSize: '0.75rem', color: '#dc2626' }}>{errors.items}</p>
            )}

            {lines.length === 0 ? (
                <p style={{ padding: '1rem 0', textAlign: 'center', color: '#94a3b8', fontSize: '0.8rem' }}>
                    {hasOrder ? 'No items on this purchase order.' : 'Select a purchase order to load its items.'}
                </p>
            ) : (
                <div style={{ overflowX: 'auto' }}>
                    <table style={{
                        width: '100%', minWidth: compact ? 620 : undefined,
                        borderCollapse: 'collapse', fontSize: '0.8rem',
                    }}>
                        <thead>
                            <tr style={{ background: '#fff' }}>
                                <th style={{ ...HEAD, width: '2.5rem' }}>#</th>
                                <th style={HEAD}>Item</th>
                                <th style={{ ...HEAD, width: '6rem', textAlign: 'right' }}>PO Qty</th>
                                <th style={{ ...HEAD, width: '7rem' }}>Received <span style={{ color: '#f87171' }}>*</span></th>
                                <th style={{ ...HEAD, width: '7rem', textAlign: 'right' }}>Unit Cost</th>
                                <th style={{ ...HEAD, width: '12rem' }}>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            {lines.map((line, index) => (
                                <tr key={line.purchase_order_item_id ?? index} style={{ background: '#fff' }}>
                                    <td style={{ ...CELL, color: '#94a3b8', textAlign: 'center' }}>{index + 1}</td>
                                    <td style={{ ...CELL, fontWeight: 500, color: '#0f172a' }}>{line.item_name}</td>
                                    <td style={{ ...CELL, textAlign: 'right', color: '#64748b' }}>{qty(line.quantity)}</td>
                                    <td style={CELL}>
                                        <input
                                            type="number" step="0.01" min="0"
                                            aria-label={`Quantity received for ${line.item_name}`}
                                            aria-invalid={lineError(index, 'quantity_received') ? true : undefined}
                                            style={FIELD}
                                            value={line.quantity_received}
                                            onChange={(e) => update(index, 'quantity_received', e.target.value)}
                                        />
                                    </td>
                                    <td style={{ ...CELL, textAlign: 'right', color: '#64748b' }}>{qty(line.unit_cost)}</td>
                                    <td style={CELL}>
                                        {/* A real radio group, not clickable spans: the
                                            choice decides whether the line raises stock,
                                            so it has to be reachable by keyboard. */}
                                        <div role="radiogroup" aria-label={`Type for ${line.item_name}`} style={{ display: 'flex', gap: '0.75rem' }}>
                                            {TYPES.map((kind) => (
                                                <label
                                                    key={kind}
                                                    style={{
                                                        display: 'inline-flex', alignItems: 'center', gap: '0.3rem',
                                                        fontSize: '0.75rem', fontWeight: 600, cursor: 'pointer',
                                                        color: line.type === kind
                                                            ? (kind === 'inventory' ? '#2563eb' : '#92400e')
                                                            : '#94a3b8',
                                                    }}
                                                >
                                                    <input
                                                        type="radio"
                                                        aria-label={`${kind === 'inventory' ? 'Inventory' : 'Consumable'} for ${line.item_name}`}
                                                        name={`grn-type-${index}`}
                                                        value={kind}
                                                        checked={line.type === kind}
                                                        onChange={() => update(index, 'type', kind)}
                                                        style={{ accentColor: kind === 'inventory' ? '#2563eb' : '#d97706' }}
                                                    />
                                                    {kind === 'inventory' ? 'Inventory' : 'Consumable'}
                                                </label>
                                            ))}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {lines.map((line, index) => (
                ['quantity_received', 'item_id'].map((field) => (
                    lineError(index, field) ? (
                        <p key={`${index}-${field}`} style={{ marginTop: '0.5rem', fontSize: '0.75rem', color: '#dc2626' }}>
                            Row {index + 1}: {lineError(index, field)}
                        </p>
                    ) : null
                ))
            ))}
        </FormSection>
    );
}
