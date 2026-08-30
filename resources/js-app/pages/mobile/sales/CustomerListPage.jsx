import { useMemo, useState } from 'react';
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
    const [query, setQuery] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const { showToast } = useToast();

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return customers;
        return customers.filter((c) =>
            [c.name, c.contact_person, c.phone, c.email].some((field) => String(field ?? '').toLowerCase().includes(q))
        );
    }, [customers, query]);

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

    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <h1 style={{ fontSize: 18, fontWeight: 700 }}>Customers</h1>
                <Button onClick={() => { setEditing(null); setModalOpen(true); }}>New</Button>
            </div>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Search customers…"
                    aria-label="Search customers"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {query ? `${filtered.length} of ${customers.length} customers` : `${customers.length} customers`}
                </div>
            </div>

            {filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {query ? 'No customers match that search.' : 'No customers yet.'}
                </p>
            )}

            {filtered.map((customer) => (
                <div key={customer.id} style={{ border: '1px solid #e2e8f0', borderRadius: 8, padding: 12, marginBottom: 8 }}>
                    <div onClick={() => { setEditing(customer); setModalOpen(true); }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                            <span style={{ fontWeight: 600 }}>{customer.name}</span>
                            <span style={{ fontSize: 13, color: Number(customer.outstanding_balance) > 0 ? '#dc2626' : '#64748b' }}>
                                {money(customer.outstanding_balance)}
                            </span>
                        </div>
                        <div style={{ fontSize: 13, color: '#64748b' }}>{customer.contact_person || '—'}</div>
                        <div style={{ fontSize: 12, color: '#94a3b8' }}>{customer.phone || 'No phone'}</div>
                        <div style={{ fontSize: 12, color: customer.is_active ? '#16a34a' : '#dc2626' }}>
                            {customer.is_active ? 'Active' : 'Inactive'}
                        </div>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 8 }}>
                        <Button variant="link-danger" onClick={() => setDeleting(customer)}>Delete</Button>
                    </div>
                </div>
            ))}

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
        </div>
    );
}
