import { useEffect, useState } from 'react';
import Table from '../../components/ui/Table';
import Button from '../../components/ui/Button';
import Modal from '../../components/ui/Modal';
import ConfirmModal from '../../components/ui/ConfirmModal';
import SupplierForm from '../../components/purchase/supplier/SupplierForm';
import { supplierTableColumns } from '../../components/purchase/supplier/SupplierRow';
import { apiGet, apiPost, apiPut, apiDelete } from '../../api/client';
import { useToast } from '../../components/ui/Toast';

export default function SupplierListPage() {
    const [suppliers, setSuppliers] = useState([]);
    const [editing, setEditing] = useState(null);
    const [formOpen, setFormOpen] = useState(false);
    const [pendingDelete, setPendingDelete] = useState(null);
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);
    const { showToast } = useToast();

    const load = () => apiGet('/purchase/suppliers').then((res) => setSuppliers(res.data));

    useEffect(() => { load(); }, []);

    const openCreate = () => { setEditing(null); setErrors({}); setFormOpen(true); };
    const openEdit = (supplier) => { setEditing(supplier); setErrors({}); setFormOpen(true); };

    const handleSubmit = async (values) => {
        setSubmitting(true);
        try {
            if (editing) {
                await apiPut(`/purchase/suppliers/${editing.id}`, values);
                showToast('Supplier updated.', 'success');
            } else {
                await apiPost('/purchase/suppliers', values);
                showToast('Supplier created.', 'success');
            }
            setFormOpen(false);
            await load();
        } catch (err) {
            setErrors(err.errors ? Object.fromEntries(Object.entries(err.errors).map(([k, v]) => [k, v[0]])) : {});
            showToast(err.message ?? 'Error', 'error');
        } finally {
            setSubmitting(false);
        }
    };

    const handleDelete = async () => {
        try {
            await apiDelete(`/purchase/suppliers/${pendingDelete.id}`);
            showToast('Supplier deleted.', 'success');
            setPendingDelete(null);
            await load();
        } catch (err) {
            showToast(err.message ?? 'Error', 'error');
        }
    };

    return (
        <div>
            <div className="flex justify-between items-center mb-4">
                <h1 className="text-xl font-semibold text-gray-800">Suppliers</h1>
                <Button onClick={openCreate}>New Supplier</Button>
            </div>

            <Table
                columns={supplierTableColumns({ onEdit: openEdit, onDelete: setPendingDelete })}
                rows={suppliers}
                rowKey={(row) => row.id}
                searchPlaceholder="Search suppliers…"
            />

            <Modal open={formOpen} title={editing ? 'Edit Supplier' : 'Add Supplier'} onClose={() => setFormOpen(false)}>
                <SupplierForm
                    initialValues={editing ?? {}}
                    errors={errors}
                    submitting={submitting}
                    onSubmit={handleSubmit}
                />
            </Modal>

            <ConfirmModal
                open={!!pendingDelete}
                title="Delete supplier?"
                body={pendingDelete ? `This will permanently remove "${pendingDelete.name}".` : ''}
                onConfirm={handleDelete}
                onCancel={() => setPendingDelete(null)}
            />
        </div>
    );
}
