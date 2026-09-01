import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import CustomerForm from '../../../components/sales/customer/CustomerForm';
import CustomerTable from '../../../components/sales/customer/CustomerTable';
import useCustomerList from '../../../components/sales/customer/useCustomerList';

export default function CustomerListPage() {
    const c = useCustomerList();

    return (
        <div>
            <div className="page-header">
                <div>
                    <h1 className="page-title">Customers</h1>
                    <p className="page-subtitle">Manage your customer directory</p>
                </div>
                <button type="button" onClick={c.openCreate} className="btn-primary">+ Add Customer</button>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12, gap: 12 }}>
                <div style={{ position: 'relative' }}>
                    <svg
                        style={{ position: 'absolute', left: 11, top: '50%', transform: 'translateY(-50%)', color: '#94a3b8', pointerEvents: 'none' }}
                        width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                    </svg>
                    <input
                        type="text"
                        value={c.query}
                        onChange={(e) => c.setQuery(e.target.value)}
                        placeholder="Search name, contact, email, phone…"
                        aria-label="Search customers"
                        autoComplete="off"
                        style={{ padding: '8px 14px 8px 34px', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13.5, width: 340, outline: 'none' }}
                    />
                </div>
                <div style={{ fontSize: 12.5, color: '#94a3b8', whiteSpace: 'nowrap' }}>
                    {c.query ? `${c.filtered.length} of ${c.customers.length} customers` : `${c.customers.length} customers`}
                </div>
            </div>

            <CustomerTable customers={c.filtered} onEdit={c.openEdit} onDelete={c.setDeleting} />

            <Modal
                open={c.modalOpen}
                title={c.editing ? `Edit ${c.editing.name}` : 'Add Customer'}
                onClose={() => c.setModalOpen(false)}
            >
                <CustomerForm customer={c.editing} onSaved={c.handleSaved} onCancel={() => c.setModalOpen(false)} />
            </Modal>
            <ConfirmModal
                open={!!c.deleting}
                title="Delete this customer?"
                body={c.deleting
                    ? `"${c.deleting.name}" will be permanently removed. A customer with sales history is deactivated instead.`
                    : ''}
                onConfirm={c.handleDeleteConfirmed}
                onCancel={() => c.setDeleting(null)}
            />
        </div>
    );
}
