import { useMemo, useState } from 'react';
import Card from '../../../components/ui/Card';
import Table from '../../../components/ui/Table';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import Button from '../../../components/ui/Button';
import WarehouseForm from '../../../components/inventory/warehouse/WarehouseForm';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete } from '../../../api/client';
import { useToast } from '../../../components/ui/Toast';

export default function WarehouseListPage() {
    const { items: warehouses, upsertItem, removeItem, refetch } = useLiveList({
        endpoint: '/inventory/warehouses',
        channel: 'inventory',
        event: '.warehouse.saved',
        deleteEvent: '.warehouse.deleted',
        mergeKey: 'id',
        errorMessage: 'Failed to load warehouses.',
    });
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const { showToast } = useToast();

    function handleSaved(warehouse) {
        upsertItem(warehouse);
        setModalOpen(false);
        showToast('Warehouse saved.', 'success');
    }

    async function handleDeleteConfirmed() {
        const warehouse = deleting;
        setDeleting(null);
        try {
            const result = await apiDelete(`/inventory/warehouses/${warehouse.id}`);
            if (result.deactivated) {
                showToast(result.message, 'info');
                await refetch();
            } else {
                removeItem(warehouse.id);
                showToast('Warehouse deleted.', 'success');
            }
        } catch (err) {
            showToast(err.message || 'Failed to delete warehouse.', 'error');
        }
    }

    const columns = useMemo(() => [
        { key: 'code', label: 'Code' },
        { key: 'name', label: 'Name' },
        { key: 'location', label: 'Location', render: (row) => row.location || '—' },
        { key: 'is_active', label: 'Active', render: (row) => (row.is_active ? 'Yes' : 'No') },
        {
            key: 'actions',
            label: '',
            render: (row) => (
                <div style={{ display: 'flex', gap: 8 }}>
                    <Button variant="link" onClick={() => { setEditing(row); setModalOpen(true); }}>Edit</Button>
                    <Button variant="link-danger" onClick={() => setDeleting(row)}>Delete</Button>
                </div>
            ),
        },
    ], []);

    return (
        <Card title="Warehouses">
            <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 12 }}>
                <Button onClick={() => { setEditing(null); setModalOpen(true); }}>New Warehouse</Button>
            </div>

            <Table columns={columns} rows={warehouses} rowKey={(row) => row.id} searchPlaceholder="Search warehouses…" />

            <Modal open={modalOpen} title={editing ? 'Edit Warehouse' : 'New Warehouse'} onClose={() => setModalOpen(false)}>
                <WarehouseForm warehouse={editing} onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
            <ConfirmModal
                open={!!deleting}
                title="Delete warehouse?"
                body={deleting ? `This will permanently remove "${deleting.name}". Warehouses holding stock are deactivated instead.` : ''}
                onConfirm={handleDeleteConfirmed}
                onCancel={() => setDeleting(null)}
            />
        </Card>
    );
}
