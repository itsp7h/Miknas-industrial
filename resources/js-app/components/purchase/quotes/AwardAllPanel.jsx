import { bd } from './quoteFormat';

/**
 * One row per supplier, each with "Award all": everything that supplier
 * quoted, awarded in one go — for when one supplier wins the whole request
 * (or is the only one who quoted), rather than awarding item by item.
 *
 * Shown disabled with the reason when it cannot be used (CLAUDE.md #14).
 */
export default function AwardAllPanel({ suppliers, canAward, onAwardAll, compact = false }) {
    if (!suppliers?.length) return null;

    return (
        <div style={{
            background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12, padding: compact ? 12 : 16, marginBottom: 16,
        }}>
            <div style={{ fontSize: 12.5, fontWeight: 700, color: '#0f172a' }}>Award a whole quote</div>
            <div style={{ fontSize: 12, color: '#64748b', marginBottom: 10 }}>
                Give one supplier every item they quoted, with one reason — or award item by item below.
            </div>

            {suppliers.map((s) => {
                const remaining = s.quoted - s.awarded;
                const reason = !canAward
                    ? 'You do not have permission to award on this request'
                    : (remaining === 0 ? `Everything ${s.supplier} quoted is already awarded to them` : undefined);

                return (
                    <div key={s.quote_id} data-testid="award-all-row" style={{
                        display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10,
                        flexWrap: compact ? 'wrap' : 'nowrap', padding: '8px 0', borderTop: '1px solid #f1f5f9',
                    }}>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontSize: 13.5, fontWeight: 600, color: '#0f172a' }}>
                                {s.supplier}
                                {/* Their own quotation, as attached in the portal. */}
                                {s.document && (
                                    <a
                                        href={s.document.url} target="_blank" rel="noreferrer"
                                        title={s.document.name}
                                        style={{ marginLeft: 8, fontSize: 12, fontWeight: 600, color: '#2563eb', textDecoration: 'none' }}
                                    >
                                        📎 Their quotation
                                    </a>
                                )}
                            </div>
                            <div style={{ fontSize: 12, color: '#64748b' }}>
                                {s.quoted} {s.quoted === 1 ? 'item' : 'items'} quoted · {bd(s.total)} before VAT
                                {s.awarded > 0 && <span style={{ color: '#15803d' }}> · {s.awarded} awarded to them</span>}
                                {s.held_elsewhere > 0 && <span style={{ color: '#b45309' }}> · {s.held_elsewhere} awarded to others</span>}
                            </div>
                        </div>
                        <button
                            type="button" disabled={!!reason} title={reason}
                            onClick={() => onAwardAll({ ...s, remaining })}
                            style={{
                                flexShrink: 0, padding: '7px 14px', background: '#f59e0b', color: '#fff', border: 'none',
                                borderRadius: 8, fontSize: 12.5, fontWeight: 700,
                                width: compact ? '100%' : undefined,
                                ...(reason ? { opacity: 0.5, cursor: 'not-allowed' } : { cursor: 'pointer' }),
                            }}
                        >
                            {remaining > 0 && remaining < s.quoted ? `Award remaining ${remaining}` : 'Award all'}
                        </button>
                    </div>
                );
            })}
        </div>
    );
}
