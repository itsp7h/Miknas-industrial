import { useState } from 'react';
import Modal from '../../../components/ui/Modal';
import SupplierForm from '../../../components/purchase/supplier/SupplierForm';
import useLiveList from '../../../hooks/useLiveList';
import { useToast } from '../../../components/ui/Toast';

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

    function handleSaved(supplier) {
        upsertItem(supplier);
        setModalOpen(false);
        showToast('Supplier saved.', 'success');
    }

    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <h1 style={{ fontSize: 18, fontWeight: 700 }}>Suppliers</h1>
                <button onClick={() => { setEditing(null); setModalOpen(true); }}>New</button>
            </div>
            {suppliers.map((supplier) => (
                <div
                    key={supplier.id}
                    onClick={() => { setEditing(supplier); setModalOpen(true); }}
                    style={{ border: '1px solid #e2e8f0', borderRadius: 8, padding: 12, marginBottom: 8 }}
                >
                    <div style={{ fontWeight: 600 }}>{supplier.name}</div>
                    <div style={{ fontSize: 13, color: '#64748b' }}>{supplier.category ?? '—'}</div>
                    <div style={{ fontSize: 12, color: supplier.is_active ? '#16a34a' : '#dc2626' }}>
                        {supplier.is_active ? 'Active' : 'Inactive'}
                    </div>
                </div>
            ))}
            <Modal open={modalOpen} title={editing ? 'Edit Supplier' : 'New Supplier'} onClose={() => setModalOpen(false)}>
                <SupplierForm supplier={editing} onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
        </div>
    );
}
