import { useEffect, useState } from 'react';
import useLiveList from '../../../hooks/useLiveList';
import { echo } from '../../../echo';

const STAGE_LABELS = {
    draft: 'Draft', gm_approval: 'GM Approval', rfq: 'RFQ', quoting: 'Quoting',
    comparison: 'Comparison', lpo: 'LPO', receiving: 'Receiving', payment: 'Payment', complete: 'Complete',
};

export default function PipelineBoardPage() {
    const { items, setItems } = useLiveList({
        endpoint: '/purchase/pipeline',
        channel: 'purchase',
        event: '.purchase-request.created',
        mergeKey: 'id',
        errorMessage: 'Failed to load the purchase pipeline.',
    });

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
    const active = items.filter((r) => r.stage !== 'complete');
    const completed = items.filter((r) => r.stage === 'complete');
    const rows = tab === 'active' ? active : completed;

    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <h1 style={{ fontSize: 18, fontWeight: 700 }}>Purchase Pipeline</h1>
                <button onClick={() => window.mprModalOpen && window.mprModalOpen()}>+ New Request</button>
            </div>
            <div style={{ display: 'flex', gap: 8, marginBottom: 12 }}>
                <button onClick={() => setTab('active')}>Active ({active.length})</button>
                <button onClick={() => setTab('completed')}>Completed ({completed.length})</button>
            </div>
            {rows.length === 0 ? (
                <p style={{ fontSize: 13, color: '#94a3b8', textAlign: 'center', padding: '24px 0' }}>No requests.</p>
            ) : (
                rows.map((row) => (
                    <a
                        key={row.id}
                        href={`/purchase/pipeline/${row.id}`}
                        style={{
                            display: 'block', border: '1px solid #e2e8f0', borderRadius: 8,
                            padding: 12, marginBottom: 8, textDecoration: 'none', color: 'inherit',
                        }}
                    >
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <div style={{ fontWeight: 700 }}>{row.request_number}</div>
                            <span style={{
                                fontSize: 11, fontWeight: 700, padding: '3px 10px', borderRadius: 20,
                                background: row.stage === 'complete' ? '#dcfce7' : '#fffbeb',
                                color: row.stage === 'complete' ? '#15803d' : '#92400e',
                            }}>
                                {STAGE_LABELS[row.stage] ?? row.stage}
                            </span>
                        </div>
                        <div style={{ fontSize: 13, color: '#374151', marginTop: 4 }}>{row.project_name || '—'}</div>
                        <div style={{ fontSize: 12, color: '#64748b', marginTop: 2 }}>
                            {row.department || '—'} · {row.requested_by_name || '—'}
                        </div>
                        <div style={{ fontSize: 11, color: '#94a3b8', marginTop: 2 }}>{row.date}</div>
                    </a>
                ))
            )}
        </div>
    );
}
