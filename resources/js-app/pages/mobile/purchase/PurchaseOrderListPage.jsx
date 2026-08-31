import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import Button from '../../../components/ui/Button';
import PurchaseOrderForm from '../../../components/purchase/order/PurchaseOrderForm';
import { STATUS_LABELS, STATUS_COLOURS, formatDate, money } from '../../../components/purchase/order/statuses';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete, apiGet } from '../../../api/client';
import { useToast } from '../../../components/ui/Toast';

export default function PurchaseOrderListPage() {
    const { items: orders, upsertItem, removeItem } = useLiveList({
        endpoint: '/purchase/orders',
        channel: 'purchase',
        event: '.purchase-order.saved',
        deleteEvent: '.purchase-order.deleted',
        mergeKey: 'id',
        errorMessage: 'Failed to load purchase orders.',
    });
    const [query, setQuery] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const { showToast } = useToast();

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return orders;
        return orders.filter((order) =>
            [order.po_number, order.supplier_name, STATUS_LABELS[order.status] ?? order.status]
                .some((field) => String(field ?? '').toLowerCase().includes(q))
        );
    }, [orders, query]);

    function handleSaved(order) {
        upsertItem(order);
        setModalOpen(false);
        showToast('Purchase order saved.', 'success');
    }

    async function openEdit(order) {
        try {
            const full = await apiGet(`/purchase/orders/${order.id}`);
            setEditing(full.data);
            setModalOpen(true);
        } catch (err) {
            showToast(err.message || 'Could not open that order.', 'error');
        }
    }

    async function handleDeleteConfirmed() {
        const order = deleting;
        setDeleting(null);
        try {
            await apiDelete(`/purchase/orders/${order.id}`);
            removeItem(order.id);
            showToast('Purchase order deleted.', 'success');
        } catch (err) {
            showToast(err.message || 'Failed to delete the order.', 'error');
        }
    }

    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <h1 style={{ fontSize: 18, fontWeight: 700 }}>Purchase Orders</h1>
                <Button onClick={() => { setEditing(null); setModalOpen(true); }}>+ New</Button>
            </div>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Search purchase orders…"
                    aria-label="Search purchase orders"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {query ? `${filtered.length} of ${orders.length} orders` : `${orders.length} orders`}
                </div>
            </div>

            {filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {query ? 'No purchase orders match that search.' : 'No purchase orders found.'}
                </p>
            )}

            {filtered.map((order) => (
                <div key={order.id} style={{ border: '1px solid #e2e8f0', borderRadius: 8, padding: 12, marginBottom: 8 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <Link to={`/app/purchase/orders/${order.id}`} style={{ fontWeight: 600, color: '#2563eb', fontFamily: 'monospace' }}>
                            {order.po_number}
                        </Link>
                        <span style={{ fontWeight: 700 }}>{money(order.total_amount)}</span>
                    </div>
                    <div style={{ fontSize: 13, color: '#64748b' }}>{order.supplier_name ?? '—'}</div>
                    <div style={{ fontSize: 12, color: '#94a3b8' }}>
                        {formatDate(order.po_date)}
                        {order.expected_delivery_date && ` · expected ${formatDate(order.expected_delivery_date)}`}
                    </div>
                    <div style={{ fontSize: 12, color: STATUS_COLOURS[order.status], fontWeight: 600, marginTop: 4 }}>
                        {STATUS_LABELS[order.status] ?? order.status}
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 8 }}>
                        <Button variant="link" onClick={() => openEdit(order)}>Edit</Button>
                        <Button variant="link-danger" onClick={() => setDeleting(order)}>Delete</Button>
                    </div>
                </div>
            ))}

            <Modal
                open={modalOpen}
                title={editing ? `Edit ${editing.po_number}` : 'New Purchase Order'}
                onClose={() => setModalOpen(false)}
            >
                <PurchaseOrderForm order={editing} onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
            <ConfirmModal
                open={!!deleting}
                title="Delete this purchase order?"
                body={deleting ? `${deleting.po_number} will be permanently removed.` : ''}
                onConfirm={handleDeleteConfirmed}
                onCancel={() => setDeleting(null)}
            />
        </div>
    );
}
