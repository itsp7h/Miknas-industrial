import { useMemo, useRef, useState } from 'react';
import Card from '../../../components/ui/Card';
import Table from '../../../components/ui/Table';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import SupplierForm from '../../../components/purchase/supplier/SupplierForm';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete, apiPostForm } from '../../../api/client';
import { useToast } from '../../../components/ui/Toast';

const COLUMNS = [
    { key: 'name', label: 'Name' },
    { key: 'category', label: 'Category' },
    { key: 'is_active', label: 'Active', render: (row) => (row.is_active ? 'Yes' : 'No') },
];

export default function SupplierListPage() {
    const { items: suppliers, upsertItem, removeItem, refetch } = useLiveList({
        endpoint: '/purchase/suppliers',
        channel: 'purchase',
        event: '.supplier.saved',
        deleteEvent: '.supplier.deleted',
        mergeKey: 'id',
        errorMessage: 'Failed to load suppliers.',
    });
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const fileInputRef = useRef(null);
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

    async function handleDeleteConfirmed() {
        const supplier = deleting;
        setDeleting(null);
        try {
            await apiDelete(`/purchase/suppliers/${supplier.id}`);
            removeItem(supplier.id);
            showToast('Supplier deleted.', 'success');
        } catch (err) {
            showToast(err.message || 'Failed to delete supplier.', 'error');
        }
    }

    async function handleImport(e) {
        const file = e.target.files?.[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('file', file);
        try {
            const result = await apiPostForm('/purchase/suppliers/import', formData);
            showToast(
                `${result.imported} added, ${result.updated} updated, ${result.skipped} skipped.`,
                'success'
            );
            await refetch();
        } catch (err) {
            showToast(err.message || 'Failed to import suppliers.', 'error');
        } finally {
            if (fileInputRef.current) fileInputRef.current.value = '';
        }
    }

    const columnsWithActions = useMemo(
        () => [
            ...COLUMNS,
            {
                key: 'actions',
                label: '',
                render: (row) => (
                    <div style={{ display: 'flex', gap: 8 }}>
                        <button onClick={() => openEdit(row)}>Edit</button>
                        <button onClick={() => setDeleting(row)}>Delete</button>
                    </div>
                ),
            },
        ],
        []
    );

    return (
        <Card title="Suppliers">
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 8, marginBottom: 12 }}>
                <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                    <label style={{ cursor: 'pointer' }}>
                        Import
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".xlsx,.xls"
                            style={{ display: 'none' }}
                            onChange={handleImport}
                            aria-label="Import"
                        />
                    </label>
                    <a href="/api/v1/purchase/suppliers/template">Download Template</a>
                    <a href="/api/v1/purchase/suppliers/export-pdf">Export PDF</a>
                </div>
                <button onClick={openCreate}>New Supplier</button>
            </div>
            <Table columns={columnsWithActions} rows={suppliers} rowKey={(row) => row.id} searchPlaceholder="Search suppliers…" />
            <Modal open={modalOpen} title={editing ? 'Edit Supplier' : 'New Supplier'} onClose={() => setModalOpen(false)}>
                <SupplierForm supplier={editing} onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
            <ConfirmModal
                open={!!deleting}
                title="Delete supplier?"
                body={deleting ? `This will permanently remove "${deleting.name}".` : ''}
                onConfirm={handleDeleteConfirmed}
                onCancel={() => setDeleting(null)}
            />
        </Card>
    );
}
