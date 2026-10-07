import { bd } from './quoteFormat';

const TH = {
    padding: '8px 10px', color: '#64748b', fontWeight: 600, fontSize: 11,
    textTransform: 'uppercase',
};

const TAG = {
    fontSize: 11, fontWeight: 700, padding: '2px 8px', borderRadius: 4,
};

/**
 * One request item beside every supplier's offer for it. The lowest valid offer
 * gets a green row and a LOWEST tag; once an item is awarded no other supplier
 * on it can be awarded, so their buttons disappear rather than failing.
 */
export default function ItemQuoteCard({ item, canAward, onAward, onShowDetail, onEditUnit, compact = false }) {
    return (
        <div style={{
            background: '#fff', borderRadius: 14, boxShadow: '0 2px 10px rgba(0,0,0,.05)',
            overflow: 'hidden', alignSelf: 'start',
        }}>
            <div style={{
                padding: '16px 20px', borderBottom: '1px solid #f1f5f9', display: 'flex',
                justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8,
            }}>
                <div>
                    <div style={{ fontSize: 15, fontWeight: 700, color: '#0f172a' }}>{item.description}</div>
                    <div style={{ fontSize: 12, color: '#94a3b8', marginTop: 2 }}>
                        Qty: {item.quantity} {item.unit}
                    </div>
                </div>
                <span style={{
                    background: item.badge.background, color: item.badge.colour, padding: '4px 12px',
                    borderRadius: 12, fontWeight: 700, fontSize: 11, whiteSpace: 'nowrap',
                }}>
                    {item.badge.label}
                </span>
            </div>

            <div style={{ padding: '8px 20px 16px', overflowX: 'auto' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13, minWidth: compact ? 0 : 460 }}>
                    <thead>
                        <tr>
                            <th style={{ ...TH, textAlign: 'left' }}>Supplier</th>
                            <th style={{ ...TH, textAlign: 'center' }}>Unit Price</th>
                            <th style={{ ...TH, textAlign: 'center' }}>Total</th>
                            <th style={{ ...TH, textAlign: 'center' }}>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {item.rows.map((row, index) => {
                            const line = row.line;
                            const meta = [
                                row.reference ? `Ref ${row.reference}` : null,
                                row.lead_time_days !== null && row.lead_time_days !== undefined ? `${row.lead_time_days} days` : null,
                                row.payment_terms,
                                row.notes,
                            ].filter(Boolean);

                            return (
                                <tr
                                    key={line?.id ?? `${row.supplier}-${index}`}
                                    style={{ borderTop: '1px solid #f8fafc', background: row.is_min ? '#f0fdf4' : undefined }}
                                >
                                    <td style={{ padding: '8px 10px', textAlign: 'left', color: '#0f172a', fontWeight: 500 }}>
                                        {row.supplier}
                                        {row.document && (
                                            <a
                                                href={row.document.url} target="_blank" rel="noreferrer"
                                                title={`Open ${row.supplier}'s own quotation (${row.document.name})`}
                                                aria-label={`Open ${row.supplier}'s quotation`}
                                                style={{ marginLeft: 6, fontSize: 12, textDecoration: 'none' }}
                                            >
                                                📎
                                            </a>
                                        )}
                                        {meta.length > 0 && (
                                            <div style={{ fontSize: 10, color: '#94a3b8', fontWeight: 400, marginTop: 2 }}>
                                                {meta.join(' · ')}
                                            </div>
                                        )}
                                        {/* The supplier quoted something slightly different
                                            from what was asked for. */}
                                        {line?.supplier_description && (
                                            <div style={{ fontSize: 10, color: '#64748b', fontStyle: 'italic', marginTop: 2 }}>
                                                &quot;{line.supplier_description}&quot;
                                                <span style={{
                                                    background: '#fef3c7', color: '#92400e', fontSize: 9, fontWeight: 700,
                                                    padding: '1px 5px', borderRadius: 3, border: '1px solid #fde68a',
                                                    fontStyle: 'normal', marginLeft: 2,
                                                }}>
                                                    adjusted
                                                </span>
                                            </div>
                                        )}
                                        {/* Quoted in the supplier's own unit: what they
                                            offered, and what it is in ours. */}
                                        {line?.supplier_unit && !line.not_available && (
                                            <div style={{
                                                fontSize: 10, color: '#92400e', background: '#fffbeb', border: '1px solid #fde68a',
                                                borderRadius: 4, padding: '3px 6px', marginTop: 4, fontWeight: 400,
                                                display: 'flex', alignItems: 'center', gap: 6, flexWrap: 'wrap',
                                            }}>
                                                <span>
                                                    Quoted in <strong>{line.supplier_unit}</strong>: {line.supplier_quantity} {line.supplier_unit}
                                                    {' '}@ {bd(line.supplier_unit_price)}
                                                    {/* No factor: what one holds in ours is set on the GRN. */}
                                                    {line.unit_factor > 0
                                                        ? <> · 1 {line.supplier_unit} = {line.unit_factor} {item.unit} = {line.quantity} {item.unit}</>
                                                        : <> · conversion to {item.unit} set on the GRN</>}
                                                </span>
                                                {onEditUnit && (() => {
                                                    const reason = !canAward
                                                        ? 'You do not have permission to change a quote'
                                                        : (line.is_awarded ? 'Unaward this line before changing its unit' : undefined);

                                                    return (
                                                        <button
                                                            type="button"
                                                            disabled={!!reason}
                                                            title={reason}
                                                            onClick={() => onEditUnit({
                                                                lineId: line.id, item: item.description, supplier: row.supplier,
                                                                unit: item.unit, requested: item.quantity,
                                                                supplierUnit: line.supplier_unit, factor: line.unit_factor,
                                                                supplierQty: line.supplier_quantity, supplierPrice: line.supplier_unit_price,
                                                            })}
                                                            style={{
                                                                fontSize: 10, fontWeight: 700, color: '#92400e', background: '#fff',
                                                                border: '1px solid #fcd34d', borderRadius: 4, padding: '1px 6px',
                                                                cursor: reason ? 'not-allowed' : 'pointer', opacity: reason ? 0.5 : 1,
                                                            }}
                                                        >
                                                            ✎ Edit
                                                        </button>
                                                    );
                                                })()}
                                            </div>
                                        )}
                                    </td>

                                    {!line && (
                                        <>
                                            <td colSpan={2} style={{ padding: '8px 10px', textAlign: 'center' }}>
                                                <span style={{ ...TAG, color: '#64748b', background: '#f1f5f9', border: '1px solid #e2e8f0' }}>
                                                    Not quoted
                                                </span>
                                            </td>
                                            <td />
                                        </>
                                    )}

                                    {line?.not_available && (
                                        <>
                                            <td colSpan={2} style={{ padding: '8px 10px', textAlign: 'center' }}>
                                                <span style={{ ...TAG, color: '#dc2626', background: '#fef2f2', border: '1px solid #fecaca' }}>
                                                    Not available
                                                </span>
                                            </td>
                                            <td />
                                        </>
                                    )}

                                    {line && !line.not_available && (
                                        <>
                                            <td style={{
                                                padding: '8px 10px', textAlign: 'center', fontWeight: 600,
                                                color: line.is_awarded ? '#15803d' : (row.is_min ? '#2563eb' : '#0f172a'),
                                            }}>
                                                {bd(line.unit_price)}
                                                {line.supplier_unit && (
                                                    <span
                                                        title={line.unit_factor > 0 ? undefined : `Their total over the ${item.unit} we asked for. The real price per ${item.unit} is set on the GRN.`}
                                                        style={{ fontSize: 10, color: '#92400e', fontWeight: 500 }}
                                                    >
                                                        {' '}/{item.unit}{line.unit_factor > 0 ? '' : ' (est.)'}
                                                    </span>
                                                )}
                                                {line.is_vatable && (
                                                    <span title="VAT applicable" style={{
                                                        fontSize: 9, fontWeight: 700, color: '#0ea5e9', background: '#e0f2fe',
                                                        padding: '1px 4px', borderRadius: 3, border: '1px solid #bae6fd', marginLeft: 2,
                                                    }}>
                                                        VAT
                                                    </span>
                                                )}
                                            </td>
                                            <td style={{ padding: '8px 10px', textAlign: 'center', color: '#64748b' }}>
                                                {bd(line.total_price)}
                                            </td>
                                            <td style={{ padding: '8px 10px', textAlign: 'center' }}>
                                                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6 }}>
                                                    {row.is_min && <span style={{ fontSize: 10, fontWeight: 700, color: '#2563eb' }}>LOWEST</span>}
                                                    {line.is_awarded && (
                                                        <button
                                                            type="button"
                                                            onClick={() => onShowDetail({
                                                                lineId: line.id, item: item.description, supplier: row.supplier,
                                                                unitPrice: line.unit_price, totalPrice: line.total_price,
                                                                reason: line.award_reason, awardedAt: line.awarded_at,
                                                                awardedBy: line.awarded_by,
                                                            })}
                                                            style={{
                                                                fontSize: 10, fontWeight: 700, color: '#15803d', background: '#dcfce7',
                                                                padding: '3px 10px', borderRadius: 10, border: 'none', cursor: 'pointer',
                                                            }}
                                                        >
                                                            ✓ AWARDED
                                                        </button>
                                                    )}
                                                    {/* Offered on every other line too: awarding one
                                                        moves the award from whoever holds it. */}
                                                    {!line.is_awarded && canAward && (
                                                        <button
                                                            type="button"
                                                            onClick={() => onAward({
                                                                lineId: line.id, item: item.description,
                                                                supplier: row.supplier, unitPrice: line.unit_price,
                                                                replaces: item.awarded_supplier,
                                                            })}
                                                            style={{
                                                                padding: '5px 12px', background: '#f59e0b', color: '#fff', border: 'none',
                                                                borderRadius: 6, fontSize: 11, fontWeight: 700, cursor: 'pointer',
                                                            }}
                                                        >
                                                            {item.has_award ? 'Award instead →' : 'Award →'}
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </>
                                    )}
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
