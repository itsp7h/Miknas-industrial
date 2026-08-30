import { useMemo, useState } from 'react';
import Card from '../../../components/ui/Card';
import Table from '../../../components/ui/Table';
import Modal from '../../../components/ui/Modal';
import Button from '../../../components/ui/Button';
import StockMovementForm, { TYPE_LABELS } from '../../../components/inventory/movement/StockMovementForm';
import useLiveList from '../../../hooks/useLiveList';
import { useToast } from '../../../components/ui/Toast';

const TYPE_COLOURS = { in: '#16a34a', out: '#dc2626', adjustment: '#ca8a04' };

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString(undefined, {
        year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit',
    });
}

export default function StockMovementPage() {
    const { items: movements, upsertItem } = useLiveList({
        endpoint: '/inventory/movements',
        channel: 'inventory',
        event: '.stock-movement.recorded',
        mergeKey: 'id',
        errorMessage: 'Failed to load stock movements.',
    });
    const [modalOpen, setModalOpen] = useState(false);
    const { showToast } = useToast();

    function handleSaved(movement) {
        upsertItem(movement);
        setModalOpen(false);
        showToast('Stock movement recorded.', 'success');
    }

    const columns = useMemo(() => [
        { key: 'created_at', label: 'Date', render: (row) => formatDate(row.created_at) },
        { key: 'item_name', label: 'Item', render: (row) => row.item_name ?? '—' },
        { key: 'warehouse_name', label: 'Warehouse', render: (row) => row.warehouse_name ?? '—' },
        {
            key: 'type',
            label: 'Type',
            render: (row) => (
                <span style={{ color: TYPE_COLOURS[row.type], fontWeight: 600 }}>
                    {TYPE_LABELS[row.type] ?? row.type}
                </span>
            ),
        },
        { key: 'quantity', label: 'Quantity' },
        { key: 'notes', label: 'Notes', render: (row) => row.notes || '—' },
    ], []);

    return (
        <Card title="Stock Movements">
            <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 12 }}>
                <Button onClick={() => setModalOpen(true)}>New Movement</Button>
            </div>

            <Table columns={columns} rows={movements} rowKey={(row) => row.id} searchPlaceholder="Search movements…" />

            <Modal open={modalOpen} title="Record Stock Movement" onClose={() => setModalOpen(false)}>
                <StockMovementForm onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
        </Card>
    );
}
