import { FormSection, TABLE_CELL as CELL, TABLE_FIELD as FIELD, TABLE_HEAD as HEAD } from '../../ui/FormModal';
import { qty } from './grnStyles';

const TYPES = ['inventory', 'consumable'];

/**
 * The lines being received, drawn as the MPR and purchase order tables are.
 * Rows are not added or removed here — they come from the chosen purchase
 * order — so there is no "+ Add Row"; what is editable is the quantity
 * actually received and whether the line lands in stock or is consumed.
 *
 * A consumable line is used up on a project rather than stocked, so choosing
 * Consumable asks which project, from `projects` (the order's company's own,
 * or every project when the company is not on file).
 *
 * PO Qty and Unit Cost are shown but read-only: they belong to the order.
 * `unit_cost` still rides along in the payload, as the API accepts it and
 * the stock movement is costed from it.
 */
export default function GrnItemRows({ lines, projects = [], defaultProjectId = '', accent, compact, errors, hasOrder, onChange }) {
    const lineError = (index, field) => errors[`items.${index}.${field}`];

    function update(index, field, value) {
        onChange(lines.map((line, i) => (i === index ? { ...line, [field]: value } : line)));
    }

    // Switching to Consumable starts from the MPR's project, when it names one.
    function setType(index, kind) {
        onChange(lines.map((line, i) => (i === index ? {
            ...line,
            type: kind,
            project_id: kind === 'consumable' ? (line.project_id || defaultProjectId) : '',
        } : line)));
    }

    const rowErrors = lines.map((line, index) => (
        ['quantity_received', 'supplier_quantity', 'item_id', 'project_id'].map((field) => (
            lineError(index, field) ? (
                <p key={`${index}-${field}`} style={{ marginTop: '0.5rem', fontSize: '0.75rem', color: '#dc2626' }}>
                    Row {index + 1}: {lineError(index, field)}
                </p>
            ) : null
        ))
    ));

    // On a phone each line is a block (SteelERP-Mobile-Designs-V2's "Items
    // received"): what was ordered, a stepper for what arrived, and the
    // Inventory / Consumable choice as a segmented control. Same fields and
    // handlers as the table.
    if (compact) {
        return (
            <FormSection accent={accent} title={lines.length ? `Items received (${lines.length})` : 'Items received'}>
                {errors.items && <p style={{ margin: '0 0 8px', fontSize: 13, color: '#dc2626' }}>{errors.items}</p>}
                {lines.length === 0 && (
                    <p style={{ padding: '8px 0', textAlign: 'center', color: '#94A3B8', fontSize: 14, margin: 0 }}>
                        {hasOrder ? 'No items on this purchase order.' : 'Select a purchase order to load its items.'}
                    </p>
                )}
                {lines.map((line, index) => (
                    <GrnLineCard
                        key={line.purchase_order_item_id ?? index}
                        line={line} index={index} projects={projects}
                        error={(field) => lineError(index, field)}
                        onQuantity={(value) => update(index, 'quantity_received', value)}
                        onType={(kind) => setType(index, kind)}
                        onProject={(value) => update(index, 'project_id', value)}
                    />
                ))}
                {rowErrors}
            </FormSection>
        );
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
                                    <td style={{ ...CELL, fontWeight: 500, color: '#0f172a' }}>
                                        {line.item_name}
                                        {line.supplier_unit && (
                                            <div style={{ marginTop: '0.2rem', fontSize: '0.7rem', fontWeight: 400, color: '#92400e' }}>
                                                Ordered in the supplier&apos;s unit ({line.supplier_unit}). Count it in {line.supplier_unit};
                                                {' '}it is converted to {line.unit_of_measure || 'our unit'} on the GRN before confirming.
                                            </div>
                                        )}
                                    </td>
                                    <td style={{ ...CELL, textAlign: 'right', color: '#64748b' }}>
                                        {qty(line.quantity)}{line.supplier_unit ? ` ${line.supplier_unit}` : ''}
                                    </td>
                                    <td style={CELL}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
                                            <input
                                                type="number" step="0.001" min="0"
                                                aria-label={line.supplier_unit
                                                    ? `${line.supplier_unit} received for ${line.item_name}`
                                                    : `Quantity received for ${line.item_name}`}
                                                aria-invalid={lineError(index, line.supplier_unit ? 'supplier_quantity' : 'quantity_received') ? true : undefined}
                                                style={FIELD}
                                                value={line.quantity_received}
                                                onChange={(e) => update(index, 'quantity_received', e.target.value)}
                                            />
                                            {line.supplier_unit && (
                                                <span style={{ fontSize: '0.7rem', fontWeight: 700, color: '#92400e' }}>{line.supplier_unit}</span>
                                            )}
                                        </div>
                                    </td>
                                    <td style={{ ...CELL, textAlign: 'right', color: '#64748b' }}>
                                        {qty(line.unit_cost)}{line.supplier_unit ? ` / ${line.supplier_unit}` : ''}
                                    </td>
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
                                                        onChange={() => setType(index, kind)}
                                                        style={{ accentColor: kind === 'inventory' ? '#2563eb' : '#d97706' }}
                                                    />
                                                    {kind === 'inventory' ? 'Inventory' : 'Consumable'}
                                                </label>
                                            ))}
                                        </div>
                                        {/* A boxed field of its own, not TABLE_FIELD: that one is
                                            borderless because the cell is its edge, and under the
                                            radios it read as plain text rather than a choice. */}
                                        {line.type === 'consumable' && (
                                            <label style={{ display: 'block', marginTop: '0.5rem' }}>
                                                <span style={{ display: 'block', fontSize: '0.7rem', fontWeight: 600, color: '#92400e', marginBottom: '0.2rem' }}>
                                                    Project <span style={{ color: '#f87171' }}>*</span>
                                                </span>
                                                <select
                                                    aria-label={`Project for ${line.item_name}`}
                                                    aria-invalid={lineError(index, 'project_id') ? true : undefined}
                                                    required
                                                    className={`form-select${lineError(index, 'project_id') ? ' form-input-error' : ''}`}
                                                    style={{ width: '100%', fontSize: '0.8rem', padding: '0.3rem 2rem 0.3rem 0.5rem', background: '#fffbeb' }}
                                                    value={line.project_id ?? ''}
                                                    onChange={(e) => update(index, 'project_id', e.target.value)}
                                                >
                                                    <option value="">— Select project —</option>
                                                    {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                                </select>
                                            </label>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {rowErrors}
        </FormSection>
    );
}

function GrnLineCard({ line, index, projects, error, onQuantity, onType, onProject }) {
    const unit = line.supplier_unit || line.unit_of_measure || '';
    const step = (by) => onQuantity(String(Math.max(0, Math.round(((Number(line.quantity_received) || 0) + by) * 1000) / 1000)));
    const stepper = {
        width: 44, height: 48, border: 0, background: 'transparent', color: '#2563EB', fontSize: 24, cursor: 'pointer', flexShrink: 0,
    };

    return (
        <div style={{
            display: 'flex', flexDirection: 'column', gap: 14,
            ...(index > 0 ? { borderTop: '1px solid #F1F5F9', marginTop: 18, paddingTop: 18 } : {}),
        }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                <div style={{ minWidth: 0 }}>
                    <div style={{ fontSize: 17, fontWeight: 600, color: '#0F172A' }}>{line.item_name}</div>
                    <div style={{ fontSize: 14, color: '#475569', marginTop: 2 }}>
                        PO qty {qty(line.quantity)}{unit ? ` ${unit}` : ''} · {qty(line.unit_cost)}{unit ? ` / ${unit}` : ''}
                    </div>
                </div>
                <span style={{ fontSize: 14, fontWeight: 600, color: '#475569', flexShrink: 0 }}>#{index + 1}</span>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12 }}>
                <span style={{ fontSize: 16, color: '#0F172A' }}>Received</span>
                <div style={{
                    display: 'flex', alignItems: 'center', border: `1px solid ${error(line.supplier_unit ? 'supplier_quantity' : 'quantity_received') ? '#F87171' : '#CBD5E1'}`,
                    borderRadius: 14, background: '#FFFFFF', width: 200, maxWidth: '62%',
                }}>
                    <button type="button" aria-label={`Fewer received for ${line.item_name}`} onClick={() => step(-1)} style={stepper}>−</button>
                    <input
                        type="number" inputMode="decimal" step="0.001" min="0"
                        aria-label={line.supplier_unit
                            ? `${line.supplier_unit} received for ${line.item_name}`
                            : `Quantity received for ${line.item_name}`}
                        value={line.quantity_received}
                        onChange={(e) => onQuantity(e.target.value)}
                        style={{ flex: 1, minWidth: 0, height: 48, border: 0, background: 'transparent', textAlign: 'center', fontSize: 17, fontWeight: 600, outline: 'none' }}
                    />
                    {unit && <span style={{ fontSize: 14, fontWeight: 600, color: '#0F172A' }}>{unit}</span>}
                    <button type="button" aria-label={`More received for ${line.item_name}`} onClick={() => step(1)} style={stepper}>+</button>
                </div>
            </div>

            <div role="radiogroup" aria-label={`Type for ${line.item_name}`} style={{
                display: 'grid', gridTemplateColumns: '1fr 1fr', background: '#E2E8F0', borderRadius: 12, padding: 3, gap: 3,
            }}>
                {TYPES.map((kind) => {
                    const on = line.type === kind;

                    return (
                        <button
                            key={kind} type="button" role="radio" aria-checked={on}
                            aria-label={`${kind === 'inventory' ? 'Inventory' : 'Consumable'} for ${line.item_name}`}
                            onClick={() => onType(kind)}
                            style={{
                                height: 40, border: 0, borderRadius: 9, font: 'inherit', fontSize: 15, cursor: 'pointer',
                                fontWeight: on ? 600 : 500, background: on ? '#FFFFFF' : 'transparent',
                                color: on ? (kind === 'inventory' ? '#2563EB' : '#B45309') : '#334155',
                                boxShadow: on ? '0 1px 3px rgba(15,23,42,0.12)' : 'none',
                            }}
                        >
                            {kind === 'inventory' ? 'Inventory' : 'Consumable'}
                        </button>
                    );
                })}
            </div>

            {line.type === 'consumable' && (
                <div>
                    <label className="form-label" htmlFor={`grn-line-${index}-project`}>Project <span style={{ color: '#f87171' }}>*</span></label>
                    <select
                        id={`grn-line-${index}-project`} required
                        aria-label={`Project for ${line.item_name}`}
                        className={`form-select${error('project_id') ? ' form-input-error' : ''}`}
                        value={line.project_id ?? ''}
                        onChange={(e) => onProject(e.target.value)}
                    >
                        <option value="">— Select project —</option>
                        {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                    </select>
                </div>
            )}

            {line.supplier_unit && (
                <div style={{
                    display: 'flex', gap: 10, padding: '12px 14px', borderRadius: 14, background: '#FFF7ED',
                    color: '#9A3412', fontSize: 14, lineHeight: 1.45,
                }}>
                    <span aria-hidden="true" style={{ flexShrink: 0 }}>ⓘ</span>
                    <span>
                        Ordered in the supplier&apos;s unit ({line.supplier_unit}). Count it in {line.supplier_unit} — it is
                        converted to {line.unit_of_measure || 'our unit'} on the GRN before confirming.
                    </span>
                </div>
            )}
        </div>
    );
}
