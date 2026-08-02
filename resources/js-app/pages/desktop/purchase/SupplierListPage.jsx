import { useEffect, useState } from 'react';
import Card from '../../../components/ui/Card';
import Table from '../../../components/ui/Table';
import Modal from '../../../components/ui/Modal';
import SupplierForm from '../../../components/purchase/supplier/SupplierForm';
import { apiGet } from '../../../api/client';
import { echo } from '../../../echo';
import { useToast } from '../../../components/ui/Toast';

const COLUMNS = [
    { key: 'name', label: 'Name' },
    { key: 'category', label: 'Category' },
    { key: 'is_active', label: 'Active', render: (row) => (row.is_active ? 'Yes' : 'No') },
];

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

    function openCreate() {
        setEditing(null);
        setModalOpen(true);
    }

    function openEdit(supplier) {
        setEditing(supplier);
        setModalOpen(true);
    }

    function handleSaved(supplier) {
        setSuppliers((prev) => {
            const exists = prev.some((s) => s.id === supplier.id);
            return exists ? prev.map((s) => (s.id === supplier.id ? supplier : s)) : [...prev, supplier];
        });
        setModalOpen(false);
        showToast('Supplier saved.', 'success');
    }

    const columnsWithActions = [
        ...COLUMNS,
        { key: 'actions', label: '', render: (row) => <button onClick={() => openEdit(row)}>Edit</button> },
    ];

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
