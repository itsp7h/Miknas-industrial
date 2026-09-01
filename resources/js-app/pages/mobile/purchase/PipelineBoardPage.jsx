import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import useLiveList from '../../../hooks/useLiveList';
import { echo } from '../../../echo';
import { useRequestModal } from '../../../components/purchase/requests/RequestModalProvider';

const STAGE_LABELS = {
    draft: 'Draft', gm_approval: 'GM Approval', rfq: 'RFQ', quoting: 'Quoting',
    comparison: 'Comparison', lpo: 'LPO', receiving: 'Receiving', payment: 'Payment', complete: 'Complete',
};

// Mirrors App\Policies\PurchaseRequestPolicy::ACTIVE_PIPELINE_STAGES exactly — kept
// in sync by hand since the frontend can't import PHP constants.
const ACTIVE_PIPELINE_STAGES = ['rfq', 'quoting', 'comparison', 'lpo', 'receiving', 'payment', 'complete'];

export default function PipelineBoardPage({
    currentUserId, canViewAllPurchaseRequests, canViewActivePipeline, canViewOwnPurchaseRequests,
} = {}) {
    const { openNew } = useRequestModal();
    const { items, setItems } = useLiveList({
        endpoint: '/purchase/pipeline',
        channel: 'purchase',
        mergeKey: 'id',
        errorMessage: 'Failed to load the purchase pipeline.',
    });

    // .purchase-request.created goes out unfiltered on the shared `private-purchase`
    // channel to every authenticated user (the API endpoint filters by permission,
    // the broadcast doesn't). Mirror the API's own filter here so a view-own user
    // doesn't see other users' new requests, and a view-active-pipeline user doesn't
    // see newly-created draft-stage requests.
    useEffect(() => {
        const ch = echo.private('purchase');
        const handleCreated = (payload) => {
            const allowed = canViewAllPurchaseRequests
                || (canViewActivePipeline && ACTIVE_PIPELINE_STAGES.includes(payload.stage))
                || (canViewOwnPurchaseRequests && payload.requested_by_id === currentUserId);
            if (!allowed) return;
            setItems((prev) => (
                prev.some((item) => item.id === payload.id)
                    ? prev.map((item) => (item.id === payload.id ? payload : item))
                    : [...prev, payload]
            ));
        };
        // An edit rewrites these very columns, and PurchaseRequestUpdated
        // broadcasts the same payload shape, so one handler upserts both.
        ch.listen('.purchase-request.created', handleCreated);
        ch.listen('.purchase-request.updated', handleCreated);
        return () => {
            ch.stopListening('.purchase-request.created');
            ch.stopListening('.purchase-request.updated');
        };
    }, [currentUserId, canViewAllPurchaseRequests, canViewActivePipeline, canViewOwnPurchaseRequests, setItems]);

    // .purchase-request.stage-changed carries only {id, request_number, stage} — merge
    // it shallowly onto the matching row so project_name/department/etc. survive.
    useEffect(() => {
        const ch = echo.private('purchase');
        const handleStageChanged = (payload) => {
            setItems((prev) => prev.map((item) => (
                item.id === payload.id ? { ...item, ...payload } : item
            )));
        };
        ch.listen('.purchase-request.stage-changed', handleStageChanged);
        return () => ch.stopListening('.purchase-request.stage-changed');
    }, [setItems]);

    const [tab, setTab] = useState('active');
    const [query, setQuery] = useState('');

    const active = items.filter((r) => r.stage !== 'complete');
    const completed = items.filter((r) => r.stage === 'complete');
    const rows = tab === 'active' ? active : completed;

    const filteredRows = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return rows;
        return rows.filter((row) => (
            [row.request_number, row.project_name, row.department, row.requested_by_name]
                .filter(Boolean)
                .some((field) => field.toLowerCase().includes(q))
        ));
    }, [rows, query]);

    return (
        <div style={{ minHeight: '100%', boxSizing: 'border-box', width: '100%' }}>
            {/* Hero header */}
            <div style={{
                boxSizing: 'border-box', width: '100%', padding: '20px 18px', color: '#fff', position: 'relative',
                overflow: 'hidden', borderRadius: 18,
                background: 'linear-gradient(135deg, #2563eb 0%, #4f46e5 100%)',
            }}>
                <div style={{
                    position: 'absolute', top: -32, right: -32, width: 144, height: 144,
                    borderRadius: '9999px', background: 'rgba(255,255,255,0.1)',
                }} />
                <div style={{ position: 'relative', display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12 }}>
                    <div style={{ minWidth: 0 }}>
                        <p style={{ fontSize: 11, fontWeight: 600, letterSpacing: '.08em', textTransform: 'uppercase', color: 'rgba(255,255,255,0.7)', margin: 0 }}>
                            Purchase
                        </p>
                        <h1 style={{ fontSize: 22, fontWeight: 800, margin: '2px 0 0', lineHeight: 1.2 }}>Pipeline Board</h1>
                        <p style={{ fontSize: 13, color: 'rgba(255,255,255,0.85)', margin: '4px 0 0' }}>
                            Track requests through every approval stage
                        </p>
                    </div>
                    <button
                        onClick={openNew}
                        style={{
                            flexShrink: 0, background: '#fff', color: '#2563eb', border: 0, borderRadius: 12,
                            padding: '10px 14px', fontSize: 13, fontWeight: 700, whiteSpace: 'nowrap',
                        }}
                    >
                        + New Request
                    </button>
                </div>
            </div>

            {/* Sticky search + tabs */}
            <div style={{
                boxSizing: 'border-box', width: '100%', position: 'sticky', top: 0, zIndex: 5,
                padding: '12px 0', background: '#f8fafc',
            }}>
                <div style={{
                    display: 'flex', alignItems: 'center', gap: 8, background: '#fff', border: '1px solid #e2e8f0',
                    borderRadius: 14, padding: '8px 12px', marginBottom: 10,
                }}>
                    <span style={{ color: '#94a3b8', fontSize: 14 }}>⌕</span>
                    <input
                        type="text"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search requests…"
                        aria-label="Search requests"
                        style={{ flex: 1, minWidth: 0, border: 0, outline: 'none', fontSize: 14, background: 'transparent' }}
                    />
                </div>

                <div style={{ display: 'flex', gap: 6, background: '#e2e8f0', borderRadius: 12, padding: 4 }}>
                    <button
                        onClick={() => setTab('active')}
                        style={{
                            flex: 1, border: 0, borderRadius: 9, padding: '8px 0', fontSize: 13, fontWeight: 700,
                            background: tab === 'active' ? '#fff' : 'transparent',
                            color: tab === 'active' ? '#1e293b' : '#64748b',
                            boxShadow: tab === 'active' ? '0 1px 2px rgba(0,0,0,0.08)' : 'none',
                        }}
                    >
                        Active ({active.length})
                    </button>
                    <button
                        onClick={() => setTab('completed')}
                        style={{
                            flex: 1, border: 0, borderRadius: 9, padding: '8px 0', fontSize: 13, fontWeight: 700,
                            background: tab === 'completed' ? '#fff' : 'transparent',
                            color: tab === 'completed' ? '#1e293b' : '#64748b',
                            boxShadow: tab === 'completed' ? '0 1px 2px rgba(0,0,0,0.08)' : 'none',
                        }}
                    >
                        Completed ({completed.length})
                    </button>
                </div>

                {query.trim() !== '' && (
                    <p style={{ fontSize: 11, color: '#94a3b8', margin: '8px 2px 0' }}>
                        {filteredRows.length} of {rows.length}
                    </p>
                )}
            </div>

            {/* Cards */}
            <div style={{ padding: '12px 0 24px', display: 'flex', flexDirection: 'column', gap: 10 }}>
                {filteredRows.length === 0 ? (
                    <div style={{ textAlign: 'center', padding: '40px 16px', color: '#94a3b8' }}>
                        <p style={{ fontSize: 28, margin: 0 }}>📋</p>
                        <p style={{ fontSize: 13, margin: '8px 0 0' }}>
                            {query.trim() !== '' ? 'No matching requests.' : 'No requests.'}
                        </p>
                    </div>
                ) : (
                    filteredRows.map((row) => (
                        <Link
                            key={row.id}
                            to={`/app/purchase/pipeline/${row.id}`}
                            style={{
                                display: 'block', background: '#fff', border: '1px solid #f1f5f9', borderRadius: 16,
                                padding: 14, textDecoration: 'none', color: 'inherit',
                                boxShadow: '0 1px 2px rgba(15,23,42,0.04)',
                            }}
                        >
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 8 }}>
                                <div style={{ minWidth: 0 }}>
                                    <div style={{ fontWeight: 700, fontSize: 14, color: '#0f172a' }}>{row.request_number}</div>
                                    <div style={{ fontSize: 13, color: '#334155', marginTop: 2 }}>{row.project_name || '—'}</div>
                                </div>
                                <span style={{
                                    flexShrink: 0, fontSize: 11, fontWeight: 700, padding: '3px 10px', borderRadius: 20,
                                    background: row.stage === 'complete' ? '#dcfce7' : '#fffbeb',
                                    color: row.stage === 'complete' ? '#15803d' : '#92400e',
                                }}>
                                    {STAGE_LABELS[row.stage] ?? row.stage}
                                </span>
                            </div>
                            <div style={{
                                display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                                marginTop: 10, paddingTop: 10, borderTop: '1px solid #f1f5f9',
                            }}>
                                <div style={{ fontSize: 12, color: '#64748b', minWidth: 0 }}>
                                    {row.department || '—'} · {row.requested_by_name || '—'}
                                </div>
                                <div style={{ fontSize: 11, color: '#94a3b8', flexShrink: 0 }}>{row.date}</div>
                            </div>
                        </Link>
                    ))
                )}
            </div>
        </div>
    );
}
