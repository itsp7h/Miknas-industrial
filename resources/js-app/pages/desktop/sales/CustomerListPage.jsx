import { useMemo, useState } from 'react';
import Card from '../../../components/ui/Card';
import Table from '../../../components/ui/Table';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import Button from '../../../components/ui/Button';
import CustomerForm from '../../../components/sales/customer/CustomerForm';
import useLiveList from '../../../hooks/useLiveList';
import { apiDelete } from '../../../api/client';
import { useToast } from '../../../components/ui/Toast';

const money = (value) => Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function CustomerListPage() {
    const { items: customers, upsertItem, removeItem, refetch } = useLiveList({
        endpoint: '/sales/customers',
        channel: 'sales',
        event: '.customer.saved',
        deleteEvent: '.customer.deleted',
        mergeKey: 'id',
        errorMessage: 'Failed to load customers.',
    });
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const { showToast } = useToast();

    function handleSaved(customer) {
        upsertItem(customer);
        setModalOpen(false);
        showToast('Customer saved.', 'success');
    }

    async function handleDeleteConfirmed() {
        const customer = deleting;
        setDeleting(null);
        try {
            const result = await apiDelete(`/sales/customers/${customer.id}`);
            if (result.deactivated) {
                showToast(result.message, 'info');
                await refetch();
            } else {
                removeItem(customer.id);
                showToast('Customer deleted.', 'success');
            }
        } catch (err) {
            showToast(err.message || 'Failed to delete customer.', 'error');
        }
    }

    const columns = useMemo(() => [
        { key: 'name', label: 'Customer' },
        { key: 'contact_person', label: 'Contact', render: (row) => row.contact_person || '—' },
        { key: 'phone', label: 'Phone', render: (row) => row.phone || '—' },
        { key: 'credit_limit', label: 'Credit Limit', render: (row) => money(row.credit_limit) },
        {
            key: 'outstanding_balance',
            label: 'Outstanding',
            render: (row) => (
                <span style={{ color: Number(row.outstanding_balance) > 0 ? '#dc2626' : '#0f172a' }}>
                    {money(row.outstanding_balance)}
                </span>
            ),
        },
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
        <Card title="Customers">
            <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 12 }}>
                <Button onClick={() => { setEditing(null); setModalOpen(true); }}>New Customer</Button>
            </div>

            <Table columns={columns} rows={customers} rowKey={(row) => row.id} searchPlaceholder="Search customers…" />

            <Modal open={modalOpen} title={editing ? 'Edit Customer' : 'New Customer'} onClose={() => setModalOpen(false)}>
                <CustomerForm customer={editing} onSaved={handleSaved} onCancel={() => setModalOpen(false)} />
            </Modal>
            <ConfirmModal
                open={!!deleting}
                title="Delete customer?"
                body={deleting ? `This will permanently remove "${deleting.name}". Customers with sales history are deactivated instead.` : ''}
                onConfirm={handleDeleteConfirmed}
                onCancel={() => setDeleting(null)}
            />
        </Card>
    );
}
