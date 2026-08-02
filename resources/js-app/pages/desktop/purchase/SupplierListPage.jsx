import { useMemo, useState } from 'react';
import Card from '../../../components/ui/Card';
import Table from '../../../components/ui/Table';
import Modal from '../../../components/ui/Modal';
import SupplierForm from '../../../components/purchase/supplier/SupplierForm';
import useLiveList from '../../../hooks/useLiveList';
import { useToast } from '../../../components/ui/Toast';

const COLUMNS = [
    { key: 'name', label: 'Name' },
    { key: 'category', label: 'Category' },
    { key: 'is_active', label: 'Active', render: (row) => (row.is_active ? 'Yes' : 'No') },
];

export default function SupplierListPage() {
    const { items: suppliers, upsertItem } = useLiveList({
        endpoint: '/purchase/suppliers',
        channel: 'purchase',
        event: '.supplier.saved',
        mergeKey: 'id',
        errorMessage: 'Failed to load suppliers.',
    });
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const { showToast } = useToast();

    function openCreate() {
        setEditing(null);
        setModalOpen(true);
    }

    function openEdit(supplier) {
        setEditing(supplier);
        setModalOpen(true);
    }

    function handleSaved(supplier) {
        upsertItem(supplier);
        setModalOpen(false);
        showToast('Supplier saved.', 'success');
    }

    const columnsWithActions = useMemo(
        () => [
            ...COLUMNS,
            { key: 'actions', label: '', render: (row) => <button onClick={() => openEdit(row)}>Edit</button> },
        ],
        []
    );

    return (
        <Card title="Suppliers">
            <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 12 }}>
                <button onClick={openCreate}>New Supplier</button>
            </div>
            <Table columns={columnsWithActions} rows={suppliers} rowKey={(row) => row.id} searchPlaceholder="Search suppliers…" />
            <Modal open={modalOpen} title={editing ? 'Edit Supplier' : 'New Supplier'} onClose={() => setModalOpen(false)}>
                <SupplierForm supplier={editing} onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
        </Card>
    );
}
