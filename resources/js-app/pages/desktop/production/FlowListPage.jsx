import { useMemo, useState } from 'react';
import Card from '../../../components/ui/Card';
import Table from '../../../components/ui/Table';
import Modal from '../../../components/ui/Modal';
import Button from '../../../components/ui/Button';
import FlowForm from '../../../components/production/FlowForm';
import { qty } from '../../../components/production/statuses';
import useLiveList from '../../../hooks/useLiveList';
import { useToast } from '../../../components/ui/Toast';

// Material issues have their own page now (its Blade design put the create form
// inline under the table); this config entry stays only for the tests that cover
// the shared component until output gets its own page too.
const CONFIG = {
    'material-issue': {
        title: 'Material Issues',
        endpoint: '/production/material-issues',
        event: '.material-issue.recorded',
        dateKey: 'issue_date',
        numberKey: 'issue_number',
        numberLabel: 'Issue #',
        newLabel: 'Issue Material',
        tone: '#dc2626',
    },
    'production-output': {
        title: 'Production Output',
        endpoint: '/production/outputs',
        event: '.production-output.recorded',
        dateKey: 'output_date',
        numberKey: null,
        numberLabel: null,
        newLabel: 'Record Output',
        tone: '#16a34a',
    },
};

export default function FlowListPage({ kind }) {
    const config = CONFIG[kind];
    const { items: rows, upsertItem } = useLiveList({
        endpoint: config.endpoint,
        channel: 'production',
        event: config.event,
        mergeKey: 'id',
        errorMessage: `Failed to load ${config.title.toLowerCase()}.`,
    });
    const [modalOpen, setModalOpen] = useState(false);
    const { showToast } = useToast();

    function handleSaved(row) {
        upsertItem(row);
        setModalOpen(false);
        showToast(`${config.title.replace(/s$/, '')} recorded.`, 'success');
    }

    const columns = useMemo(() => [
        ...(config.numberKey ? [{ key: config.numberKey, label: config.numberLabel }] : []),
        { key: config.dateKey, label: 'Date' },
        { key: 'production_order_number', label: 'Order', render: (row) => row.production_order_number ?? '—' },
        { key: 'item_name', label: 'Item', render: (row) => row.item_name ?? '—' },
        { key: 'warehouse_name', label: 'Warehouse', render: (row) => row.warehouse_name ?? '—' },
        {
            key: 'quantity',
            label: 'Quantity',
            render: (row) => <span style={{ color: config.tone, fontWeight: 600 }}>{qty(row.quantity)}</span>,
        },
        { key: 'notes', label: 'Notes', render: (row) => row.notes || '—' },
    ], [config]);

    return (
        <Card title={config.title}>
            <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 12 }}>
                <Button onClick={() => setModalOpen(true)}>{config.newLabel}</Button>
            </div>

            <Table columns={columns} rows={rows} rowKey={(row) => row.id} searchPlaceholder={`Search ${config.title.toLowerCase()}…`} />

            <Modal open={modalOpen} title={config.newLabel} onClose={() => setModalOpen(false)}>
                <FlowForm kind={kind} onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
        </Card>
    );
}
