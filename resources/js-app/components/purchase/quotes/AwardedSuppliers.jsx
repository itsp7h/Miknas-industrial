import { bd } from './quoteFormat';

/**
 * The Awarded Suppliers tab: the same awards grouped by who won them, with each
 * supplier's own total — which is what the LPOs will be cut from.
 */
export default function AwardedSuppliers({ awards }) {
    if (!awards.length) {
        return (
            <div style={{
                background: '#fff', borderRadius: 14, boxShadow: '0 2px 10px rgba(0,0,0,.05)',
                padding: '50px 0', textAlign: 'center', color: '#94a3b8', fontSize: 13,
            }}>
                No items have been awarded yet.
            </div>
        );
    }

    const bySupplier = awards.reduce((groups, award) => {
        const key = award.supplier ?? '—';
        groups[key] = [...(groups[key] ?? []), award];

        return groups;
    }, {});

    const grandTotal = awards.reduce((sum, award) => sum + Number(award.total_price ?? 0), 0);

    return (
        <div style={{ maxWidth: 900 }}>
            {Object.keys(bySupplier).sort().map((supplier) => {
                const rows = bySupplier[supplier];
                const supplierTotal = rows.reduce((sum, row) => sum + Number(row.total_price ?? 0), 0);

                return (
                    <div key={supplier} style={{
                        background: '#fff', borderRadius: 14, boxShadow: '0 2px 10px rgba(0,0,0,.05)',
                        overflow: 'hidden', marginBottom: 16,
                    }}>
                        <div style={{
                            padding: '14px 20px', borderBottom: '1px solid #f1f5f9', display: 'flex',
                            justifyContent: 'space-between', alignItems: 'center', background: '#f0fdf4', gap: 8,
                        }}>
                            <div style={{ fontSize: 14, fontWeight: 700, color: '#15803d' }}>✓ {supplier}</div>
                            <div style={{ fontSize: 13, fontWeight: 700, color: '#15803d' }}>{bd(supplierTotal)}</div>
                        </div>

                        <div style={{ padding: '6px 20px 4px' }}>
                            {rows.map((row, index) => (
                                <div key={row.id} style={{ padding: '12px 0', borderTop: index ? '1px solid #f8fafc' : undefined }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 12 }}>
                                        <div>
                                            <div style={{ fontSize: 13, fontWeight: 600, color: '#0f172a' }}>{row.item}</div>
                                            <div style={{ fontSize: 11, color: '#94a3b8', marginTop: 2 }}>
                                                {row.quantity !== undefined && row.quantity !== null
                                                    ? `Qty: ${row.quantity}${row.unit ? ` ${row.unit}` : ''} · `
                                                    : ''}
                                                {bd(row.unit_price)} / unit
                                            </div>
                                        </div>
                                        <div style={{ fontSize: 13, fontWeight: 700, color: '#0f172a', whiteSpace: 'nowrap' }}>
                                            {bd(row.total_price)}
                                        </div>
                                    </div>

                                    {row.reason && (
                                        <div style={{ marginTop: 8, padding: '9px 12px', background: '#f8fafc', borderRadius: 8, border: '1px solid #f1f5f9' }}>
                                            <div style={{ fontSize: 10, fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '.05em', marginBottom: 3 }}>
                                                Reason
                                            </div>
                                            <div style={{ fontSize: 12.5, color: '#374151' }}>{row.reason}</div>
                                            {(row.awarded_by || row.awarded_at) && (
                                                <div style={{ fontSize: 10.5, color: '#94a3b8', marginTop: 4 }}>
                                                    {[row.awarded_by, row.awarded_at].filter(Boolean).join(' · ')}
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                );
            })}

            <div style={{
                padding: '14px 20px', background: '#0f172a', borderRadius: 12, display: 'flex',
                justifyContent: 'space-between', alignItems: 'center', gap: 10,
            }}>
                <div style={{ fontSize: 11, fontWeight: 600, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '.05em' }}>
                    Awarded Total
                </div>
                <div style={{ fontSize: 18, fontWeight: 700, color: '#fff' }}>{bd(grandTotal)}</div>
            </div>
        </div>
    );
}
