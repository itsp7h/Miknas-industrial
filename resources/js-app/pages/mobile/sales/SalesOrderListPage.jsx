import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import Button from '../../../components/ui/Button';
import SalesOrderForm from '../../../components/sales/order/SalesOrderForm';
import { STATUS_LABELS, STATUS_COLOURS, money } from '../../../components/sales/order/statuses';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete, apiGet, apiPatch } from '../../../api/client';
import { useToast } from '../../../components/ui/Toast';

export default function SalesOrderListPage() {
    const { items: orders, upsertItem, removeItem } = useLiveList({
        endpoint: '/sales/orders',
        channel: 'sales',
        event: '.sales-order.saved',
        deleteEvent: '.sales-order.deleted',
        mergeKey: 'id',
        errorMessage: 'Failed to load sales orders.',
    });
    const [query, setQuery] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const [confirming, setConfirming] = useState(null);
    const { showToast } = useToast();

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return orders;
        return orders.filter((o) =>
            [o.order_number, o.customer_name, STATUS_LABELS[o.status] ?? o.status]
                .some((field) => String(field ?? '').toLowerCase().includes(q))
        );
    }, [orders, query]);

    function handleSaved(order) {
        upsertItem(order);
        setModalOpen(false);
        showToast('Sales order saved.', 'success');
    }

    async function openEdit(order) {
        try {
            const full = await apiGet(`/sales/orders/${order.id}`);
            setEditing(full.data);
            setModalOpen(true);
        } catch (err) {
            showToast(err.message || 'Could not open that order.', 'error');
        }
    }

    async function handleConfirm() {
        const order = confirming;
        setConfirming(null);
        try {
            const response = await apiPatch(`/sales/orders/${order.id}/confirm`);
            upsertItem(response.data);
            showToast(`${order.order_number} confirmed.`, 'success');
        } catch (err) {
            showToast(err.message || 'Failed to confirm the order.', 'error');
        }
    }

    async function handleDeleteConfirmed() {
        const order = deleting;
        setDeleting(null);
        try {
            await apiDelete(`/sales/orders/${order.id}`);
            removeItem(order.id);
            showToast('Sales order deleted.', 'success');
        } catch (err) {
            showToast(err.message || 'Failed to delete the order.', 'error');
        }
    }

    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <h1 style={{ fontSize: 18, fontWeight: 700 }}>Sales Orders</h1>
                <Button onClick={() => { setEditing(null); setModalOpen(true); }}>New</Button>
            </div>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Search sales orders…"
                    aria-label="Search sales orders"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {query ? `${filtered.length} of ${orders.length} orders` : `${orders.length} orders`}
                </div>
            </div>

            {filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {query ? 'No sales orders match that search.' : 'No sales orders yet.'}
                </p>
            )}

            {filtered.map((order) => (
                <div key={order.id} style={{ border: '1px solid #e2e8f0', borderRadius: 8, padding: 12, marginBottom: 8 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <Link to={`/app/sales/orders/${order.id}`} style={{ fontWeight: 600, color: '#2563eb' }}>
                            {order.order_number}
                        </Link>
                        <span style={{ fontWeight: 700 }}>{money(order.total_amount)}</span>
                    </div>
                    <div style={{ fontSize: 13, color: '#64748b' }}>{order.customer_name ?? '—'}</div>
                    <div style={{ fontSize: 12, color: '#94a3b8' }}>{order.order_date}</div>
                    <div style={{ fontSize: 12, color: STATUS_COLOURS[order.status], fontWeight: 600, marginTop: 4 }}>
                        {STATUS_LABELS[order.status] ?? order.status}
                    </div>
                    {order.status === 'draft' && (
                        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 8 }}>
                            <Button variant="link" onClick={() => openEdit(order)}>Edit</Button>
                            <Button variant="link" onClick={() => setConfirming(order)}>Confirm</Button>
                            <Button variant="link-danger" onClick={() => setDeleting(order)}>Delete</Button>
                        </div>
                    )}
                </div>
            ))}

            <Modal open={modalOpen} title={editing ? `Edit ${editing.order_number}` : 'New Sales Order'} onClose={() => setModalOpen(false)}>
                <SalesOrderForm order={editing} onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
            <ConfirmModal
                open={!!confirming}
                title="Confirm this order?"
                body={confirming ? `${confirming.order_number} will be confirmed and can no longer be edited.` : ''}
                onConfirm={handleConfirm}
                onCancel={() => setConfirming(null)}
            />
            <ConfirmModal
                open={!!deleting}
                title="Delete this order?"
                body={deleting ? `${deleting.order_number} and its line items will be permanently removed.` : ''}
                onConfirm={handleDeleteConfirmed}
                onCancel={() => setDeleting(null)}
            />
        </div>
    );
}
