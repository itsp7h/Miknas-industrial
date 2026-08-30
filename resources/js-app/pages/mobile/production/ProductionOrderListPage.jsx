import { useMemo, useState } from 'react';
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
    const [query, setQuery] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const [completing, setCompleting] = useState(null);
    const { showToast } = useToast();

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return orders;
        return orders.filter((o) =>
            [o.order_number, o.product_name, PO_STATUS_LABELS[o.status] ?? o.status]
                .some((field) => String(field ?? '').toLowerCase().includes(q))
        );
    }, [orders, query]);

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

    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <h1 style={{ fontSize: 18, fontWeight: 700 }}>Production Orders</h1>
                <Button onClick={() => { setEditing(null); setModalOpen(true); }}>New</Button>
            </div>

            <div style={{ marginBottom: 12 }}>
                <input type="search" value={query} onChange={(e) => setQuery(e.target.value)}
                    placeholder="Search production orders…" aria-label="Search production orders"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full" />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {query ? `${filtered.length} of ${orders.length} orders` : `${orders.length} orders`}
                </div>
            </div>

            {filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {query ? 'No production orders match that search.' : 'No production orders yet.'}
                </p>
            )}

            {filtered.map((order) => (
                <div key={order.id} style={{ border: '1px solid #e2e8f0', borderRadius: 8, padding: 12, marginBottom: 8 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <span style={{ fontWeight: 600 }}>{order.order_number}</span>
                        <span style={{ fontSize: 12, fontWeight: 600, color: PO_STATUS_COLOURS[order.status] }}>
                            {PO_STATUS_LABELS[order.status] ?? order.status}
                        </span>
                    </div>
                    <div style={{ fontSize: 13, color: '#64748b' }}>{order.product_name ?? '—'}</div>
                    <div style={{ fontSize: 12, color: '#94a3b8' }}>
                        {qty(order.quantity_produced)} of {qty(order.quantity_to_produce)} made · {order.production_date}
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 8 }}>
                        {order.status === 'planned' && <Button variant="link" onClick={() => { setEditing(order); setModalOpen(true); }}>Edit</Button>}
                        {order.status === 'planned' && <Button variant="link" onClick={() => transition(order, 'start')}>Start</Button>}
                        {order.status === 'in_progress' && <Button variant="link" onClick={() => setCompleting(order)}>Complete</Button>}
                        {order.status === 'planned' && <Button variant="link-danger" onClick={() => setDeleting(order)}>Delete</Button>}
                    </div>
                </div>
            ))}

            <Modal open={modalOpen} title={editing ? `Edit ${editing.order_number}` : 'New Production Order'} onClose={() => setModalOpen(false)}>
                <ProductionOrderForm order={editing} onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
            <ConfirmModal
                open={!!completing}
                title="Mark this order complete?"
                body={completing ? `${completing.order_number} will be marked complete and cannot be reopened.` : ''}
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
        </div>
    );
}
