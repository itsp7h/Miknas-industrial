import { useMemo, useState } from 'react';
import Card from '../../../components/ui/Card';
import Table from '../../../components/ui/Table';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import Button from '../../../components/ui/Button';
import ProductionOrderForm from '../../../components/production/ProductionOrderForm';
import { PO_STATUS_LABELS, PO_STATUS_COLOURS, qty } from '../../../components/production/statuses';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete, apiPatch } from '../../../api/client';
import { useToast } from '../../../components/ui/Toast';

export default function ProductionOrderListPage() {
    const { items: orders, upsertItem, removeItem } = useLiveList({
        endpoint: '/production/orders',
        channel: 'production',
        event: '.production-order.saved',
        deleteEvent: '.production-order.deleted',
        mergeKey: 'id',
        errorMessage: 'Failed to load production orders.',
    });
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const [completing, setCompleting] = useState(null);
    const { showToast } = useToast();

    function handleSaved(order) {
        upsertItem(order);
        setModalOpen(false);
        showToast('Production order saved.', 'success');
    }

    async function transition(order, action) {
        try {
            const response = await apiPatch(`/production/orders/${order.id}/${action}`);
            upsertItem(response.data);
            showToast(`${order.order_number} ${action === 'start' ? 'started' : 'completed'}.`, 'success');
        } catch (err) {
            showToast(err.message || `Failed to ${action} that order.`, 'error');
        }
    }

    async function handleDeleteConfirmed() {
        const order = deleting;
        setDeleting(null);
        try {
            await apiDelete(`/production/orders/${order.id}`);
            removeItem(order.id);
            showToast('Production order deleted.', 'success');
        } catch (err) {
            showToast(err.message || 'Failed to delete that order.', 'error');
        }
    }

    const columns = useMemo(() => [
        { key: 'order_number', label: 'Order #' },
        { key: 'product_name', label: 'Product', render: (row) => row.product_name ?? '—' },
        {
            key: 'quantity_produced',
            label: 'Progress',
            render: (row) => `${qty(row.quantity_produced)} / ${qty(row.quantity_to_produce)}`,
        },
        { key: 'production_date', label: 'Date' },
        {
            key: 'status',
            label: 'Status',
            render: (row) => (
                <span style={{ color: PO_STATUS_COLOURS[row.status], fontWeight: 600 }}>
                    {PO_STATUS_LABELS[row.status] ?? row.status}
                </span>
            ),
        },
        {
            key: 'actions',
            label: '',
            render: (row) => (
                <div style={{ display: 'flex', gap: 8 }}>
                    {row.status === 'planned' && <Button variant="link" onClick={() => { setEditing(row); setModalOpen(true); }}>Edit</Button>}
                    {row.status === 'planned' && <Button variant="link" onClick={() => transition(row, 'start')}>Start</Button>}
                    {row.status === 'in_progress' && <Button variant="link" onClick={() => setCompleting(row)}>Complete</Button>}
                    {row.status === 'planned' && <Button variant="link-danger" onClick={() => setDeleting(row)}>Delete</Button>}
                </div>
            ),
        },
    ], []);

    return (
        <Card title="Production Orders">
            <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 12 }}>
                <Button onClick={() => { setEditing(null); setModalOpen(true); }}>New Production Order</Button>
            </div>

            <Table columns={columns} rows={orders} rowKey={(row) => row.id} searchPlaceholder="Search production orders…" />

            <Modal open={modalOpen} title={editing ? `Edit ${editing.order_number}` : 'New Production Order'} onClose={() => setModalOpen(false)}>
                <ProductionOrderForm order={editing} onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
            <ConfirmModal
                open={!!completing}
                title="Mark this order complete?"
                body={completing ? `${completing.order_number} will be marked complete and the production managers are notified. It cannot be reopened.` : ''}
                onConfirm={() => { const o = completing; setCompleting(null); transition(o, 'complete'); }}
                onCancel={() => setCompleting(null)}
            />
            <ConfirmModal
                open={!!deleting}
                title="Delete this order?"
                body={deleting ? `${deleting.order_number} will be permanently removed.` : ''}
                onConfirm={handleDeleteConfirmed}
                onCancel={() => setDeleting(null)}
            />
        </Card>
    );
}
