import { Link } from 'react-router-dom';
import { CARD, CARD_TITLE, INVITATION_STATUS, PILL, PO_STATUS, bd } from './pipelineStyles';

const ROW = {
    display: 'flex', justifyContent: 'space-between', alignItems: 'center',
    gap: 8, fontSize: 12, padding: '8px 10px', borderRadius: 8,
};

function DetailRow({ label, value }) {
    return (
        <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
            <dt style={{ color: '#64748b', flexShrink: 0 }}>{label}</dt>
            <dd style={{ color: '#0f172a', fontWeight: 600, textAlign: 'right', margin: 0 }}>{value}</dd>
        </div>
    );
}

/**
 * The read-only sidebar card stack. Rendered beside the timeline on desktop and
 * stacked beneath it on mobile — same cards, same order, so the two shells show
 * the same facts.
 */
export default function PipelineSidebar({ request }) {
    const r = request;
    const capitalise = (s) => (s ? s.charAt(0).toUpperCase() + s.slice(1) : '—');

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
            <div style={CARD}>
                <h3 style={CARD_TITLE}>Request Details</h3>
                <dl style={{ display: 'flex', flexDirection: 'column', gap: 8, fontSize: 13, margin: 0 }}>
                    {r.location && <DetailRow label="Location" value={r.location} />}
                    {r.required_date_text && <DetailRow label="Required By" value={r.required_date_text} />}
                    {r.verified_by_name && <DetailRow label="Verified By" value={r.verified_by_name} />}
                    <DetailRow label="Status" value={capitalise(r.status)} />
                </dl>
            </div>

            {r.rfq_invitations.length > 0 && (
                <div style={CARD}>
                    <h3 style={CARD_TITLE}>Suppliers ({r.rfq_invitations.length})</h3>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                        {r.rfq_invitations.map((inv) => {
                            const sc = INVITATION_STATUS[inv.status] ?? INVITATION_STATUS.pending;

                            return (
                                <div key={inv.id} style={{
                                    display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                                    gap: 8, fontSize: 12, padding: '7px 0', borderBottom: '1px solid #f8fafc',
                                }}>
                                    <div>
                                        <div style={{ fontWeight: 600, color: '#0f172a' }}>{inv.supplier_name}</div>
                                        {inv.channel !== 'email' && (
                                            <div style={{ fontSize: 10, color: '#94a3b8' }}>{capitalise(inv.channel)}</div>
                                        )}
                                    </div>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                        {inv.whatsapp_link && (
                                            <a
                                                href={inv.whatsapp_link}
                                                target="_blank"
                                                rel="noreferrer"
                                                style={{
                                                    fontSize: 10, background: '#dcfce7', color: '#15803d',
                                                    padding: '2px 7px', borderRadius: 10,
                                                    textDecoration: 'none', fontWeight: 700,
                                                }}
                                            >
                                                WA
                                            </a>
                                        )}
                                        <span style={{ ...PILL, background: sc.bg, color: sc.fg }}>{sc.label}</span>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}

            {r.supplier_quotes.length > 0 && r.items.length > 0 && (
                <div style={CARD}>
                    <h3 style={CARD_TITLE}>Items ({r.items.length})</h3>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                        {r.items.map((item) => (
                            <div key={item.id} style={{
                                ...ROW,
                                background: item.is_awarded ? '#f0fdf4' : '#f8fafc',
                                border: `1px solid ${item.is_awarded ? '#bbf7d0' : '#f1f5f9'}`,
                            }}>
                                <div>
                                    <div style={{ fontWeight: 600, color: '#0f172a' }}>{item.description}</div>
                                    <div style={{ fontSize: 10, color: '#94a3b8', marginTop: 1 }}>
                                        {item.quote_supplier_names.length
                                            ? item.quote_supplier_names.join(', ')
                                            : 'No quotes yet'}
                                    </div>
                                </div>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 6, flexShrink: 0 }}>
                                    {item.is_awarded ? (
                                        <span style={{ ...PILL, background: '#dcfce7', color: '#15803d' }}>✓ Awarded</span>
                                    ) : (
                                        <span style={{
                                            ...PILL,
                                            background: item.quote_count >= 2 ? '#dbeafe' : '#f1f5f9',
                                            color: item.quote_count >= 2 ? '#1d4ed8' : '#64748b',
                                        }}>
                                            {item.quote_count} {item.quote_count === 1 ? 'quote' : 'quotes'}
                                        </span>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {r.supplier_quotes.length > 0 && (
                <div style={CARD}>
                    <h3 style={CARD_TITLE}>Quotes ({r.supplier_quotes.length})</h3>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                        {r.supplier_quotes.map((quote) => {
                            let background = '#f8fafc';
                            let border = '#f1f5f9';
                            let amountColour = '#374151';
                            if (quote.has_awarded_items) {
                                background = '#f0fdf4'; border = '#bbf7d0'; amountColour = '#15803d';
                            } else if (quote.is_lowest) {
                                background = '#eff6ff'; border = '#bfdbfe'; amountColour = '#2563eb';
                            }

                            return (
                                <div key={quote.id} style={{ ...ROW, background, border: `1px solid ${border}` }}>
                                    <div>
                                        <div style={{ fontWeight: 600, color: '#0f172a' }}>{quote.supplier_name}</div>
                                        {quote.is_lowest && (
                                            <div style={{ fontSize: 10, color: '#2563eb', fontWeight: 700, marginTop: 1 }}>
                                                LOWEST
                                            </div>
                                        )}
                                        {quote.has_awarded_items && (
                                            <div style={{ fontSize: 10, color: '#15803d', fontWeight: 700, marginTop: 1 }}>
                                                {quote.awarded_item_count} item(s) awarded
                                            </div>
                                        )}
                                    </div>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                        <div style={{ color: amountColour, fontWeight: 700 }}>{bd(quote.total_amount)}</div>
                                        {quote.has_awarded_items ? (
                                            <span style={{
                                                fontSize: 10, background: '#22c55e', color: '#fff',
                                                padding: '1px 6px', borderRadius: 10,
                                            }}>
                                                ✓
                                            </span>
                                        ) : (
                                            <svg width="12" height="12" fill="none" stroke="#94a3b8" strokeWidth="2" viewBox="0 0 24 24" style={{ flexShrink: 0 }}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                                            </svg>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}

            {r.purchase_orders.length > 0 && (
                <div style={CARD}>
                    <h3 style={CARD_TITLE}>LPOs ({r.purchase_orders.length})</h3>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                        {r.purchase_orders.map((po) => {
                            const sc = PO_STATUS[po.status] ?? PO_STATUS.draft;

                            return (
                                <Link
                                    key={po.id}
                                    to={`/app/purchase/orders/${po.id}`}
                                    style={{
                                        ...ROW, textDecoration: 'none',
                                        background: '#f8fafc', border: '1px solid #f1f5f9',
                                    }}
                                >
                                    <div>
                                        <div style={{ fontWeight: 600, color: '#0f172a' }}>{po.po_number}</div>
                                        <div style={{ fontSize: 10, color: '#94a3b8', marginTop: 1 }}>
                                            {po.supplier_name ?? '—'}
                                        </div>
                                    </div>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                        <div style={{ color: '#374151', fontWeight: 700 }}>{bd(po.total_amount)}</div>
                                        <span style={{ ...PILL, background: sc.bg, color: sc.fg }}>{sc.label}</span>
                                    </div>
                                </Link>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}
