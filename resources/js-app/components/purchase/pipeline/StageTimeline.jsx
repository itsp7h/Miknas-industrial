import { Link } from 'react-router-dom';
import { formatDate } from './pipelineStyles';

const ACTION = {
    display: 'inline-flex', alignItems: 'center', gap: 5, fontSize: 12, fontWeight: 700,
    padding: '6px 14px', borderRadius: 7, textDecoration: 'none', whiteSpace: 'nowrap',
    cursor: 'pointer', border: 'none',
};

// The Blade page's read-only button: white, slate text, 1.5px border.
const VIEW = { ...ACTION, background: '#fff', color: '#475569', border: '1.5px solid #e2e8f0' };

const EyeIcon = () => (
    <svg width="13" height="13" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
    </svg>
);

const PenIcon = () => (
    <svg width="13" height="13" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
    </svg>
);

/**
 * The RFQ send, signature capture, supplier selection, LPO issue and GRN
 * record actions exist only as modals or bare POSTs inside the Blade detail
 * page — they have no standalone destination of their own. Until each flow is
 * ported, those buttons hand off to that page rather than being dropped, which
 * would strand the action entirely.
 */
const bladeDetail = (id) => `/purchase/pipeline/${id}`;

function Dot({ done, current }) {
    let background = '#e2e8f0';
    if (done) background = '#2563eb';
    else if (current) background = '#f59e0b';

    return (
        <div style={{
            width: 18, height: 18, borderRadius: '50%', flexShrink: 0,
            display: 'flex', alignItems: 'center', justifyContent: 'center', background,
            ...(current ? { boxShadow: '0 0 0 5px #fde68a' } : {}),
        }}>
            {done && (
                <svg width="9" height="9" viewBox="0 0 8 8" fill="none">
                    <path d="M1.5 4L3 5.5L6.5 2" stroke="#fff" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
            )}
        </div>
    );
}

/**
 * The contextual line under a done/current stage label.
 *
 * `current` matters: the "Awaiting GM signature" and "Select suppliers…"
 * lines are prompts, and the Blade page showed them only on the *current*
 * stage (`@elseif($current)`). A completed stage that was skipped past with no
 * signature or no suppliers shows nothing at all.
 */
function caption(stage, r, current) {
    switch (stage) {
        case 'draft':
            return `Created by ${r.requested_by_name ?? '—'}${r.created_at ? ` · ${formatDate(r.created_at)}` : ''}`;
        case 'gm_approval':
            if (r.signature) {
                return `Signed by ${r.signature.signed_by_name ?? '—'} · ${formatDate(r.signature.signed_at)}`;
            }
            return current ? 'Awaiting GM signature' : '';
        case 'rfq': {
            const total = r.rfq_invitations.length;
            if (!total) return current ? 'Select suppliers to receive quote requests' : '';
            const unsent = r.pending_invitation_count ? ` · ${r.pending_invitation_count} unsent` : '';
            return `${total} supplier(s) selected${unsent}`;
        }
        case 'quoting':
            return `${r.supplier_quotes.length} quote(s) received · ${r.sent_invitation_count} invited`;
        case 'comparison':
            return `${r.supplier_quotes.length} quote(s) ready to compare`;
        case 'lpo':
            return r.awarded_supplier_names.length
                ? `Awarded to ${r.awarded_supplier_names.join(', ')}`
                : '';
        default:
            return '';
    }
}

function CurrentActions({ stage, r }) {
    const p = r.permissions;
    const signLabel = r.signature ? 'View Signature' : 'Sign';
    const SignIcon = r.signature ? EyeIcon : PenIcon;
    const sign = p.approve && (
        <a href={bladeDetail(r.id)} style={{ ...ACTION, background: '#7c3aed', color: '#fff' }}>
            <SignIcon /> {signLabel}
        </a>
    );

    switch (stage) {
        case 'draft':
            return sign || null;
        case 'gm_approval':
            return (
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    {sign}
                    {p.manageRfq && (
                        <a href={bladeDetail(r.id)} style={{ ...ACTION, background: '#2563eb', color: '#fff' }}>
                            🏭 Select Suppliers
                        </a>
                    )}
                </div>
            );
        case 'rfq':
            if (!p.manageRfq) return null;
            return (
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    <a href={bladeDetail(r.id)} style={{ ...ACTION, background: '#2563eb', color: '#fff' }}>
                        + Add Suppliers
                    </a>
                    {r.pending_invitation_count > 0 && (
                        <a href={bladeDetail(r.id)} style={{ ...ACTION, background: '#16a34a', color: '#fff' }}>
                            📨 Send ({r.pending_invitation_count})
                        </a>
                    )}
                </div>
            );
        case 'quoting':
            return p.manageQuotes ? (
                <a href={`/purchase/requests/${r.id}/quotes`} style={{ ...ACTION, background: '#f59e0b', color: '#fff' }}>
                    View Quotes ({r.supplier_quotes.length}) →
                </a>
            ) : null;
        case 'comparison':
            return p.manageQuotes ? (
                <a href={`/purchase/requests/${r.id}/compare`} style={{ ...ACTION, background: '#f59e0b', color: '#fff' }}>
                    Compare &amp; Award →
                </a>
            ) : null;
        case 'lpo':
            if (r.purchase_orders.length) {
                return <span style={{ ...ACTION, background: '#dcfce7', color: '#15803d' }}>✓ LPO(s) Issued</span>;
            }
            return p.generateLpo ? (
                <a href={bladeDetail(r.id)} style={{ ...ACTION, background: '#16a34a', color: '#fff' }}>
                    Issue LPO →
                </a>
            ) : null;
        case 'receiving':
            return (
                <a href={bladeDetail(r.id)} style={{ ...ACTION, background: '#16a34a', color: '#fff' }}>
                    Record GRN →
                </a>
            );
        case 'payment':
            return (
                <a href="/purchase/payments/create" style={{ ...ACTION, background: '#0f172a', color: '#fff' }}>
                    Issue Payment →
                </a>
            );
        default:
            return null;
    }
}

function DoneActions({ stage, r }) {
    const p = r.permissions;

    switch (stage) {
        case 'draft':
            return <a href={`/purchase/requests/${r.id}`} style={VIEW}><EyeIcon /> View Request</a>;
        case 'gm_approval':
            return r.signature
                ? <a href={bladeDetail(r.id)} style={VIEW}><EyeIcon /> View Signature</a>
                : null;
        case 'rfq':
            return <a href={bladeDetail(r.id)} style={VIEW}><EyeIcon /> View Suppliers</a>;
        case 'quoting':
            return p.manageQuotes
                ? <a href={`/purchase/requests/${r.id}/quotes`} style={VIEW}><EyeIcon /> View Quotes ({r.supplier_quotes.length})</a>
                : null;
        case 'comparison':
            return p.manageQuotes
                ? <a href={`/purchase/requests/${r.id}/compare`} style={VIEW}><EyeIcon /> View Comparison</a>
                : null;
        case 'lpo': {
            if (!r.purchase_orders.length) return null;
            const single = r.purchase_orders.length === 1;

            return (
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    {single ? (
                        <>
                            <Link to={`/app/purchase/orders/${r.purchase_orders[0].id}`} style={VIEW}>
                                <EyeIcon /> View LPO
                            </Link>
                            <a href={`/purchase/orders/${r.purchase_orders[0].id}/pdf`} style={VIEW}>⬇ Download PDF</a>
                        </>
                    ) : r.purchase_orders.map((po) => (
                        <a key={po.id} href={`/purchase/orders/${po.id}/pdf`} style={VIEW}>
                            ⬇ {po.supplier_name ?? 'PDF'}
                        </a>
                    ))}
                    {/* Awards can change after issuance, so re-issuing stays available. */}
                    {p.generateLpo && (
                        <a href={bladeDetail(r.id)} style={{ ...ACTION, background: '#fff', color: '#d97706', border: '1.5px solid #fde68a' }}>
                            ↻ Re-issue LPO
                        </a>
                    )}
                </div>
            );
        }
        case 'receiving':
            return <Link to="/app/purchase/grns" style={VIEW}><EyeIcon /> View GRNs</Link>;
        case 'payment':
            return <a href="/purchase/payments" style={VIEW}><EyeIcon /> View Payments</a>;
        default:
            return null;
    }
}

export default function StageTimeline({ request, compact = false }) {
    const stages = request.stages;
    const index = request.stage_index;

    return (
        <div style={{
            background: '#fff', borderRadius: 16, boxShadow: '0 2px 12px rgba(0,0,0,.06)',
            padding: compact ? 18 : 24,
        }}>
            <h2 style={{ fontSize: 14, fontWeight: 700, color: '#0f172a', margin: '0 0 20px' }}>
                Pipeline Stages
            </h2>
            <div style={{ display: 'flex', flexDirection: 'column' }}>
                {stages.map((stage, i) => {
                    const done = i < index;
                    const current = i === index;
                    const isLast = i === stages.length - 1;
                    const text = done || current ? caption(stage, request, current) : '';

                    let colour = '#94a3b8';
                    if (done) colour = '#1d4ed8';
                    else if (current) colour = '#d97706';

                    return (
                        <div key={stage} style={{ display: 'flex', alignItems: 'stretch', gap: 16 }}>
                            <div style={{
                                display: 'flex', flexDirection: 'column', alignItems: 'center',
                                width: 20, flexShrink: 0, paddingTop: 2,
                            }}>
                                <Dot done={done} current={current} />
                                {!isLast && (
                                    <div style={{
                                        width: 2, flex: 1, minHeight: 12, margin: '4px 0',
                                        background: done ? '#2563eb' : '#e2e8f0',
                                    }} />
                                )}
                            </div>

                            <div style={{ flex: 1, minWidth: 0, paddingBottom: isLast ? 0 : 16 }}>
                                <div style={{
                                    display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between',
                                    flexWrap: 'wrap', gap: 8,
                                }}>
                                    <div>
                                        <div style={{
                                            fontSize: 14, color: colour,
                                            fontWeight: current ? 700 : (done ? 600 : 400),
                                        }}>
                                            {request.stage_labels[stage]}
                                        </div>
                                        {text && (
                                            <div style={{ fontSize: 12, color: '#94a3b8', marginTop: 2 }}>{text}</div>
                                        )}
                                    </div>
                                    {current && <CurrentActions stage={stage} r={request} />}
                                    {done && <DoneActions stage={stage} r={request} />}
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
