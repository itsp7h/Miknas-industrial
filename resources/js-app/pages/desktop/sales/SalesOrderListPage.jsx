import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import Card from '../../../components/ui/Card';
import Table from '../../../components/ui/Table';
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
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const [confirming, setConfirming] = useState(null);
    const { showToast } = useToast();

    function handleSaved(order) {
        upsertItem(order);
        setModalOpen(false);
        showToast('Sales order saved.', 'success');
    }

    async function openEdit(order) {
        // The list is a summary; the form needs the order's lines.
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

    const columns = useMemo(() => [
        {
            key: 'order_number',
            label: 'Order #',
            render: (row) => (
                <Link to={`/app/sales/orders/${row.id}`} className="text-blue-600 hover:text-blue-800">
                    {row.order_number}
                </Link>
            ),
        },
        { key: 'customer_name', label: 'Customer', render: (row) => row.customer_name ?? '—' },
        { key: 'order_date', label: 'Date' },
        { key: 'total_amount', label: 'Total', render: (row) => money(row.total_amount) },
        {
            key: 'status',
            label: 'Status',
            render: (row) => (
                <span style={{ color: STATUS_COLOURS[row.status], fontWeight: 600 }}>
                    {STATUS_LABELS[row.status] ?? row.status}
                </span>
            ),
        },
        {
            key: 'actions',
            label: '',
            render: (row) => (
                <div style={{ display: 'flex', gap: 8 }}>
                    {row.status === 'draft' && <Button variant="link" onClick={() => openEdit(row)}>Edit</Button>}
                    {row.status === 'draft' && <Button variant="link" onClick={() => setConfirming(row)}>Confirm</Button>}
                    {row.status === 'draft' && <Button variant="link-danger" onClick={() => setDeleting(row)}>Delete</Button>}
                </div>
            ),
        },
    ], []);

    return (
        <Card title="Sales Orders">
            <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 12 }}>
                <Button onClick={() => { setEditing(null); setModalOpen(true); }}>New Sales Order</Button>
            </div>

            <Table columns={columns} rows={orders} rowKey={(row) => row.id} searchPlaceholder="Search sales orders…" />

            <Modal open={modalOpen} title={editing ? `Edit ${editing.order_number}` : 'New Sales Order'} onClose={() => setModalOpen(false)}>
                <SalesOrderForm order={editing} onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
            <ConfirmModal
                open={!!confirming}
                title="Confirm this order?"
                body={confirming ? `${confirming.order_number} will be confirmed and can no longer be edited. The customer is notified if they have a WhatsApp number.` : ''}
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
        </Card>
    );
}
