import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import Card from '../../../components/ui/Card';
import Table from '../../../components/ui/Table';
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
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const { showToast } = useToast();

    function handleSaved(order) {
        upsertItem(order);
        setModalOpen(false);
        showToast('Purchase order saved.', 'success');
    }

    async function openEdit(order) {
        // The list row is a summary; the edit form wants the full record.
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

    const columns = useMemo(() => [
        {
            key: 'po_number',
            label: 'PO #',
            render: (row) => (
                <Link to={`/app/purchase/orders/${row.id}`} className="text-blue-600 hover:text-blue-800" style={{ fontFamily: 'monospace' }}>
                    {row.po_number}
                </Link>
            ),
        },
        { key: 'supplier_name', label: 'Supplier', render: (row) => row.supplier_name ?? '—' },
        { key: 'po_date', label: 'Date', render: (row) => formatDate(row.po_date) },
        { key: 'expected_delivery_date', label: 'Expected Delivery', render: (row) => formatDate(row.expected_delivery_date) },
        {
            key: 'total_amount',
            label: 'Total Amount',
            render: (row) => <span style={{ fontWeight: 500 }}>{money(row.total_amount)}</span>,
        },
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
                    <Link to={`/app/purchase/orders/${row.id}`} className="text-sm font-medium text-blue-600 hover:text-blue-800">View</Link>
                    <Button variant="link" onClick={() => openEdit(row)}>Edit</Button>
                    <Button variant="link-danger" onClick={() => setDeleting(row)}>Delete</Button>
                </div>
            ),
        },
    ], []);

    return (
        <Card title="Purchase Orders">
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <p style={{ fontSize: 13, color: '#64748b', margin: 0 }}>Manage all purchase orders</p>
                <Button onClick={() => { setEditing(null); setModalOpen(true); }}>+ New PO</Button>
            </div>

            <Table columns={columns} rows={orders} rowKey={(row) => row.id} searchPlaceholder="Search purchase orders…" />

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
        </Card>
    );
}
