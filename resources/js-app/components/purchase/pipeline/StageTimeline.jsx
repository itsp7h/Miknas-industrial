import { Link } from 'react-router-dom';
import { formatDate } from './pipelineStyles';
import { liveOrders, orderLabel } from './purchaseOrders';
import { goodsReceipts, receiptCaption } from './goodsReceipts';

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
 * Actions that are modals rather than destinations. The timeline does not own
 * them — it raises the intent and the page opens the right dialog, so one modal
 * instance serves however many stage rows want it.
 */
function ActionButton({ onClick, style, children }) {
    return (
        <button type="button" onClick={onClick} style={style}>{children}</button>
    );
}

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
        case 'receiving': {
            const summary = receiptCaption(r);
            if (summary) return summary;
            return current ? 'Nothing received yet' : '';
        }
        default:
            return '';
    }
}

function CurrentActions({ stage, r, on }) {
    const p = r.permissions;
    const signLabel = r.signature ? 'View Signature' : 'Sign';
    const SignIcon = r.signature ? EyeIcon : PenIcon;
    const sign = p.approve && (
        <ActionButton onClick={() => on('signature')} style={{ ...ACTION, background: '#7c3aed', color: '#fff' }}>
            <SignIcon /> {signLabel}
        </ActionButton>
    );

    switch (stage) {
        // 'draft' is never the current step: a created request is past it.
        // Only Sign: selecting suppliers is the next step's action and lives on
        // that row. This case is reached only by a request sitting at
        // 'gm_approval' with no signature recorded.
        case 'gm_approval':
            return sign || null;
        case 'rfq':
            if (!p.manageRfq) return null;
            return (
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    <ActionButton onClick={() => on('suppliers')} style={{ ...ACTION, background: '#2563eb', color: '#fff' }}>
                        {r.rfq_invitations.length ? '+ Add Suppliers' : '🏭 Select Suppliers'}
                    </ActionButton>
                    {r.pending_invitation_count > 0 && (
                        <ActionButton onClick={() => on('send')} style={{ ...ACTION, background: '#16a34a', color: '#fff' }}>
                            📨 Send ({r.pending_invitation_count})
                        </ActionButton>
                    )}
                    {/* Who is already on the request, without leaving the page.
                        Only once there is someone to look at. */}
                    {r.rfq_invitations.length > 0 && (
                        <ActionButton onClick={() => on('view-suppliers')} style={VIEW}>
                            <EyeIcon /> View Suppliers ({r.rfq_invitations.length})
                        </ActionButton>
                    )}
                </div>
            );
        case 'quoting':
            return p.manageQuotes ? (
                <Link to={`/app/purchase/requests/${r.id}/quotes`} style={{ ...ACTION, background: '#f59e0b', color: '#fff' }}>
                    View Quotes ({r.supplier_quotes.length}) →
                </Link>
            ) : null;
        case 'comparison':
            // "Compare & award" was a second Blade URL onto the same workspace;
            // one React route serves both.
            return p.manageQuotes ? (
                <Link to={`/app/purchase/requests/${r.id}/quotes`} style={{ ...ACTION, background: '#f59e0b', color: '#fff' }}>
                    Compare &amp; Award →
                </Link>
            ) : null;
        case 'lpo':
            if (liveOrders(r).length) {
                return <span style={{ ...ACTION, background: '#dcfce7', color: '#15803d' }}>✓ LPO(s) Issued</span>;
            }
            return p.generateLpo ? (
                <ActionButton onClick={() => on('lpo')} style={{ ...ACTION, background: '#16a34a', color: '#fff' }}>
                    Issue LPO →
                </ActionButton>
            ) : null;
        case 'receiving': {
            // A draft GRN is the trap this step kept setting: the goods look
            // recorded, no stock has moved, and confirming it is what finishes
            // the step. So it is offered here rather than left to be found on
            // the GRN list.
            const { drafts } = goodsReceipts(r);

            return (
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    <ActionButton onClick={() => on('grn')} style={{ ...ACTION, background: '#16a34a', color: '#fff' }}>
                        Record GRN →
                    </ActionButton>
                    {drafts.map((grn) => (
                        <Link
                            key={grn.id}
                            to={`/app/purchase/grns/${grn.id}`}
                            style={{ ...ACTION, background: '#fffbeb', color: '#b45309', border: '1.5px solid #fde68a' }}
                        >
                            ⚠ Confirm {grn.grn_number}
                        </Link>
                    ))}
                </div>
            );
        }
        default:
            return null;
    }
}

function DoneActions({ stage, r, on }) {
    const p = r.permissions;

    switch (stage) {
        case 'draft':
            return <Link to={`/app/purchase/requests/${r.id}`} style={VIEW}><EyeIcon /> View Request</Link>;
        case 'gm_approval':
            return r.signature
                ? <ActionButton onClick={() => on('signature')} style={VIEW}><EyeIcon /> View Signature</ActionButton>
                : null;
        case 'rfq':
            return <ActionButton onClick={() => on('view-suppliers')} style={VIEW}><EyeIcon /> View Suppliers</ActionButton>;
        case 'quoting':
            return p.manageQuotes
                ? <Link to={`/app/purchase/requests/${r.id}/quotes`} style={VIEW}><EyeIcon /> View Quotes ({r.supplier_quotes.length})</Link>
                : null;
        case 'comparison':
            return p.manageQuotes
                ? <Link to={`/app/purchase/requests/${r.id}/quotes`} style={VIEW}><EyeIcon /> View Comparison</Link>
                : null;
        case 'lpo': {
            // Cancelled orders are history, not something to download: a
            // re-issue leaves the superseded LPO on the request, and listing it
            // here put two identical buttons side by side.
            const orders = liveOrders(r);
            if (!orders.length) return null;
            const single = orders.length === 1;

            return (
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    {single ? (
                        <>
                            <Link to={`/app/purchase/orders/${orders[0].id}`} style={VIEW}>
                                <EyeIcon /> View LPO
                            </Link>
                            <a href={`/purchase/orders/${orders[0].id}/pdf`} style={VIEW}>⬇ Download PDF</a>
                        </>
                    ) : orders.map((po) => (
                        // Named by supplier *and* number — a request split
                        // across suppliers is why this branch exists, but the
                        // name alone does not say which order it is.
                        <a key={po.id} href={`/purchase/orders/${po.id}/pdf`} style={VIEW}>
                            ⬇ {orderLabel(po)}
                        </a>
                    ))}
                    {/* Awards can change after issuance, so re-issuing stays available. */}
                    {p.generateLpo && (
                        <ActionButton onClick={() => on('lpo')} style={{ ...ACTION, background: '#fff', color: '#d97706', border: '1.5px solid #fde68a' }}>
                            ↻ Re-issue LPO
                        </ActionButton>
                    )}
                </div>
            );
        }
        case 'receiving': {
            const { all } = goodsReceipts(r);

            return (
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    {all.map((grn) => (
                        <Link key={grn.id} to={`/app/purchase/grns/${grn.id}`} style={VIEW}>
                            <EyeIcon /> {grn.grn_number}
                        </Link>
                    ))}
                    {!all.length && <Link to="/app/purchase/grns" style={VIEW}><EyeIcon /> View GRNs</Link>}
                </div>
            );
        }
        default:
            return null;
    }
}

/** `onAction(kind)` opens the matching dialog; the page owns them. */
export default function StageTimeline({ request, compact = false, onAction = () => {} }) {
    const stages = request.stages;
    const index = request.stage_index;

    // `stage` says where the request is; each stage's action is what moves it
    // on. Signing happens *at* 'draft' and advances to 'gm_approval'; selecting
    // suppliers happens at 'gm_approval' and advances to 'rfq'. Marking the
    // stage column's value as the step in progress therefore hangs every button
    // off the step before the one it belongs to — Sign under "Purchase
    // Request", Select Suppliers under "GM Signature".
    //
    // So the cursor passes a step once that step's work is done rather than
    // once the request has moved off it: the request exists, so Purchase
    // Request is done; the signature exists, so GM Signature is done.
    let cursor = index;
    if (request.stage === 'draft') {
        cursor = index + 1;
    } else if (request.stage === 'gm_approval' && request.signature) {
        cursor = index + 1;
    }

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
                    const done = i < cursor;
                    const current = i === cursor;
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
                                    {current && <CurrentActions stage={stage} r={request} on={onAction} />}
                                    {done && <DoneActions stage={stage} r={request} on={onAction} />}
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
