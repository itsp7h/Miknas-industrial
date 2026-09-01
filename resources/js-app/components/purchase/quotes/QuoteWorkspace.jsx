import { Link } from 'react-router-dom';
import AwardDetailModal from './AwardDetailModal';
import AwardModal from './AwardModal';
import AwardedSuppliers from './AwardedSuppliers';
import ItemQuoteCard from './ItemQuoteCard';
import useQuoteWorkspace from './useQuoteWorkspace';
import { bd, rate } from './quoteFormat';

const TAB = {
    padding: '8px 18px', border: 'none', borderRadius: 7, fontSize: 12.5,
    fontWeight: 700, cursor: 'pointer',
};

/**
 * The whole workspace, shared by both viewports: the amber header, the two tabs,
 * an item card per request item, and the grand total that takes whichever
 * supplier wins each item. `compact` only changes the grid and the header
 * padding — the decisions are identical on a phone.
 */
export default function QuoteWorkspace({ requestId, compact = false }) {
    const w = useQuoteWorkspace(requestId);
    const data = w.workspace;

    return (
        <div>
            <div style={{ marginBottom: 16 }}>
                <Link
                    to={data ? `/app/purchase/pipeline/${data.id}` : '/app/purchase/pipeline'}
                    style={{ fontSize: 13, color: '#2563eb', textDecoration: 'none', display: 'inline-flex', alignItems: 'center', gap: 5 }}
                >
                    <svg width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back to Pipeline
                </Link>
            </div>

            {w.loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}
            {!w.loading && !data && (
                <p style={{ fontSize: 14, color: '#64748b' }}>Those quotes could not be loaded.</p>
            )}

            {data && (
                <>
                    <div style={{
                        background: 'linear-gradient(135deg,#f59e0b,#d97706)', borderRadius: 16,
                        padding: compact ? '18px 20px' : '24px 28px', display: 'flex', alignItems: 'center',
                        justifyContent: 'space-between', flexWrap: 'wrap', gap: 12, marginBottom: 20,
                        boxShadow: '0 4px 24px rgba(0,0,0,.08)',
                    }}>
                        <div>
                            <div style={{ fontSize: 11, fontWeight: 600, color: 'rgba(255,255,255,.7)', textTransform: 'uppercase', letterSpacing: '.06em' }}>
                                Supplier Quotes &amp; Comparison
                            </div>
                            <div style={{ fontSize: 20, fontWeight: 700, color: '#fff', marginTop: 4 }}>{data.request_number}</div>
                            {data.project_name && (
                                <div style={{ fontSize: 13, color: 'rgba(255,255,255,.8)', marginTop: 2 }}>{data.project_name}</div>
                            )}
                        </div>
                        <div style={{ fontSize: 13, color: 'rgba(255,255,255,.9)', fontWeight: 600 }}>
                            {data.quote_count} {data.quote_count === 1 ? 'quote' : 'quotes'} received
                        </div>
                    </div>

                    {data.quote_count === 0 ? (
                        <div style={{
                            background: '#fff', borderRadius: 16, boxShadow: '0 4px 24px rgba(0,0,0,.08)',
                            padding: '60px 0', textAlign: 'center', color: '#94a3b8',
                        }}>
                            <div style={{ fontSize: 40, marginBottom: 12 }}>📬</div>
                            <div style={{ fontSize: 15, fontWeight: 600, marginBottom: 4 }}>No quotes yet</div>
                            <div style={{ fontSize: 13 }}>Waiting for suppliers to submit their quotes via the private links.</div>
                        </div>
                    ) : (
                        <>
                            <div style={{ display: 'flex', gap: 4, marginBottom: 16, background: '#f1f5f9', padding: 4, borderRadius: 10, width: 'fit-content' }}>
                                {[
                                    { key: 'comparison', label: 'Comparison & Award' },
                                    { key: 'awarded', label: 'Awarded Suppliers' },
                                ].map(({ key, label }) => (
                                    <button
                                        key={key} type="button" onClick={() => w.setTab(key)}
                                        aria-pressed={w.tab === key}
                                        style={{
                                            ...TAB,
                                            background: w.tab === key ? '#fff' : 'transparent',
                                            color: w.tab === key ? '#0f172a' : '#64748b',
                                            boxShadow: w.tab === key ? '0 1px 3px rgba(0,0,0,.08)' : 'none',
                                        }}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>

                            {w.tab === 'comparison' && (
                                <>
                                    <div style={{ fontSize: 12, color: '#64748b', marginBottom: 16 }}>
                                        Each item is its own decision — award it to whichever supplier offers the best
                                        terms for that item. Different items can go to different suppliers.
                                    </div>

                                    <div style={{
                                        display: 'grid', gap: 16,
                                        gridTemplateColumns: compact ? '1fr' : 'repeat(auto-fill,minmax(520px,1fr))',
                                    }}>
                                        {data.items.map((item) => (
                                            <ItemQuoteCard
                                                key={item.id}
                                                item={item}
                                                compact={compact}
                                                canAward={data.permissions.award}
                                                onAward={w.setAwarding}
                                                onShowDetail={w.setDetail}
                                            />
                                        ))}
                                    </div>

                                    <div style={{
                                        marginTop: 16, padding: '18px 20px', background: '#0f172a', borderRadius: 12,
                                        display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                                        flexWrap: 'wrap', gap: 10,
                                    }}>
                                        <div>
                                            <div style={{ fontSize: 11, fontWeight: 600, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '.05em' }}>
                                                Grand Total
                                            </div>
                                            <div style={{ fontSize: 11, color: '#64748b', marginTop: 2 }}>
                                                {data.fully_awarded
                                                    ? 'Based on awarded suppliers per item'
                                                    : 'Based on the awarded price where decided, lowest offer otherwise'}
                                                {data.unresolved_items > 0 && ` · ${data.unresolved_items} item(s) with no quotes excluded`}
                                            </div>
                                        </div>
                                        <div style={{ textAlign: 'right' }}>
                                            {data.vat_amount > 0 && (
                                                <div style={{ fontSize: 11, color: '#94a3b8', marginBottom: 2 }}>
                                                    Subtotal {bd(data.subtotal)} &nbsp;+&nbsp; VAT ({rate(data.vat_rate)}%) {bd(data.vat_amount)}
                                                </div>
                                            )}
                                            <div style={{ fontSize: 24, fontWeight: 700, color: '#fff' }}>{bd(data.grand_total)}</div>
                                        </div>
                                    </div>

                                    {data.fully_awarded && (
                                        <div style={{
                                            marginTop: 16, padding: 14, background: '#f0fdf4', border: '1px solid #bbf7d0',
                                            borderRadius: 10, textAlign: 'center', fontSize: 13, fontWeight: 700, color: '#15803d',
                                        }}>
                                            ✓ All quoted items have been awarded. Ready to issue LPO(s).
                                        </div>
                                    )}
                                </>
                            )}

                            {w.tab === 'awarded' && <AwardedSuppliers awards={data.awards} />}
                        </>
                    )}

                    <AwardModal target={w.awarding} onClose={() => w.setAwarding(null)} onConfirm={w.award} />
                    <AwardDetailModal
                        detail={w.detail}
                        canAward={data.permissions.award}
                        onClose={() => w.setDetail(null)}
                        onRemove={w.unaward}
                    />
                </>
            )}
        </div>
    );
}
