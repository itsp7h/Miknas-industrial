import { useRef, useState } from 'react';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import SupplierForm from '../../../components/purchase/supplier/SupplierForm';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete, apiPostForm } from '../../../api/client';
import { useToast } from '../../../components/ui/Toast';

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

    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <h1 style={{ fontSize: 18, fontWeight: 700 }}>Suppliers</h1>
                <button onClick={() => { setEditing(null); setModalOpen(true); }}>New</button>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8, marginBottom: 12 }}>
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
            {suppliers.map((supplier) => (
                <div
                    key={supplier.id}
                    style={{ border: '1px solid #e2e8f0', borderRadius: 8, padding: 12, marginBottom: 8 }}
                >
                    <div onClick={() => { setEditing(supplier); setModalOpen(true); }}>
                        <div style={{ fontWeight: 600 }}>{supplier.name}</div>
                        <div style={{ fontSize: 13, color: '#64748b' }}>{supplier.category ?? '—'}</div>
                        <div style={{ fontSize: 12, color: supplier.is_active ? '#16a34a' : '#dc2626' }}>
                            {supplier.is_active ? 'Active' : 'Inactive'}
                        </div>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 8 }}>
                        <button onClick={() => setDeleting(supplier)}>Delete</button>
                    </div>
                </div>
            ))}
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
        </div>
    );
}
