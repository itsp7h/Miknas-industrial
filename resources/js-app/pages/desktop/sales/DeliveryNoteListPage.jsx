import { useMemo, useState } from 'react';
import Card from '../../../components/ui/Card';
import Table from '../../../components/ui/Table';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import Button from '../../../components/ui/Button';
import DeliveryNoteForm from '../../../components/sales/delivery/DeliveryNoteForm';
import useLiveList from '../../../hooks/useLiveList';
import { apiPatch } from '../../../api/client';
import { useToast } from '../../../components/ui/Toast';

const STATUS_COLOURS = { draft: '#64748b', dispatched: '#7c3aed' };
const STATUS_LABELS = { draft: 'Draft', dispatched: 'Dispatched' };

export default function DeliveryNoteListPage() {
    const { items: notes, upsertItem } = useLiveList({
        endpoint: '/sales/delivery-notes',
        channel: 'sales',
        event: '.delivery-note.saved',
        mergeKey: 'id',
        errorMessage: 'Failed to load delivery notes.',
    });
    const [modalOpen, setModalOpen] = useState(false);
    const [dispatching, setDispatching] = useState(null);
    const { showToast } = useToast();

    function handleSaved(note) {
        upsertItem(note);
        setModalOpen(false);
        showToast('Delivery note created.', 'success');
    }

    async function handleDispatch() {
        const note = dispatching;
        setDispatching(null);
        try {
            const response = await apiPatch(`/sales/delivery-notes/${note.id}/dispatch`);
            upsertItem(response.data);
            showToast(`${note.delivery_number} dispatched and stock decremented.`, 'success');
        } catch (err) {
            showToast(err.message || 'Failed to dispatch that note.', 'error');
        }
    }

    const columns = useMemo(() => [
        { key: 'delivery_number', label: 'Note #' },
        { key: 'order_number', label: 'Order', render: (row) => row.order_number ?? '—' },
        { key: 'customer_name', label: 'Customer', render: (row) => row.customer_name ?? '—' },
        { key: 'warehouse_name', label: 'From', render: (row) => row.warehouse_name ?? '—' },
        { key: 'delivery_date', label: 'Date' },
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
            render: (row) => (row.status === 'draft'
                ? <Button variant="link" onClick={() => setDispatching(row)}>Dispatch</Button>
                : null),
        },
    ], []);

    return (
        <Card title="Delivery Notes">
            <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 12 }}>
                <Button onClick={() => setModalOpen(true)}>New Delivery Note</Button>
            </div>

            <Table columns={columns} rows={notes} rowKey={(row) => row.id} searchPlaceholder="Search delivery notes…" />

            <Modal open={modalOpen} title="New Delivery Note" onClose={() => setModalOpen(false)}>
                <DeliveryNoteForm onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
            <ConfirmModal
                open={!!dispatching}
                title="Dispatch this delivery note?"
                body={dispatching ? `${dispatching.delivery_number} will be dispatched, stock will be decremented at ${dispatching.warehouse_name}, and the customer is notified if they have a WhatsApp number. This cannot be undone.` : ''}
                onConfirm={handleDispatch}
                onCancel={() => setDispatching(null)}
            />
        </Card>
    );
}
