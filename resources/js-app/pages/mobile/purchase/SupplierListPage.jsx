import { useEffect, useState } from 'react';
import Modal from '../../../components/ui/Modal';
import SupplierForm from '../../../components/purchase/supplier/SupplierForm';
import { apiGet } from '../../../api/client';
import { echo } from '../../../echo';
import { useToast } from '../../../components/ui/Toast';

export default function SupplierListPage() {
    const [suppliers, setSuppliers] = useState([]);
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const { showToast } = useToast();

    useEffect(() => {
        apiGet('/purchase/suppliers').then((res) => setSuppliers(res.data));
    }, []);

    useEffect(() => {
        const channel = echo.private('purchase');
        channel.listen('.supplier.saved', (event) => {
            setSuppliers((prev) => {
                const exists = prev.some((s) => s.id === event.id);
                return exists ? prev.map((s) => (s.id === event.id ? { ...s, ...event } : s)) : [...prev, event];
            });
        });
        return () => echo.leave('purchase');
    }, []);

    function handleSaved(supplier) {
        setSuppliers((prev) => {
            const exists = prev.some((s) => s.id === supplier.id);
            return exists ? prev.map((s) => (s.id === supplier.id ? supplier : s)) : [...prev, supplier];
        });
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
