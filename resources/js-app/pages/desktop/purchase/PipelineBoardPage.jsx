import { useEffect, useState } from 'react';
import Card from '../../../components/ui/Card';
import Table from '../../../components/ui/Table';
import useLiveList from '../../../hooks/useLiveList';
import { echo } from '../../../echo';

const STAGE_LABELS = {
    draft: 'Draft', gm_approval: 'GM Approval', rfq: 'RFQ', quoting: 'Quoting',
    comparison: 'Comparison', lpo: 'LPO', receiving: 'Receiving', payment: 'Payment', complete: 'Complete',
};

const COLUMNS = [
    { key: 'request_number', label: 'Request #' },
    { key: 'project_name', label: 'Project' },
    { key: 'department', label: 'Department' },
    { key: 'requested_by_name', label: 'Requested By' },
    {
        key: 'stage',
        label: 'Stage',
        render: (row) => (
            <span style={{
                fontSize: 11, fontWeight: 700, padding: '3px 10px', borderRadius: 20,
                background: row.stage === 'complete' ? '#dcfce7' : '#fffbeb',
                color: row.stage === 'complete' ? '#15803d' : '#92400e',
            }}>
                {STAGE_LABELS[row.stage] ?? row.stage}
            </span>
        ),
    },
    { key: 'date', label: 'Date' },
    {
        key: 'link', label: '',
        render: (row) => <a href={`/purchase/pipeline/${row.id}`}>View</a>,
    },
];

export default function PipelineBoardPage() {
    const { items, setItems } = useLiveList({
        endpoint: '/purchase/pipeline',
        channel: 'purchase',
        event: '.purchase-request.created',
        mergeKey: 'id',
        errorMessage: 'Failed to load the purchase pipeline.',
    });

    // .purchase-request.stage-changed carries only {id, request_number, stage} — a
    // wholesale upsert (as useLiveList's default `event` handler does) would blank
    // out project_name/department/etc. on the existing row, so this is subscribed
    // separately here and merged shallowly onto the matching row instead.
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

    return (
        <Card title="Purchase Pipeline">
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 12 }}>
                <div style={{ display: 'flex', gap: 8 }}>
                    <button onClick={() => setTab('active')}>Active ({active.length})</button>
                    <button onClick={() => setTab('completed')}>Completed ({completed.length})</button>
                </div>
                <button onClick={() => window.mprModalOpen && window.mprModalOpen()}>+ New Request</button>
            </div>
            <Table
                columns={COLUMNS}
                rows={tab === 'active' ? active : completed}
                rowKey={(row) => row.id}
                searchPlaceholder="Search requests…"
            />
        </Card>
    );
}
