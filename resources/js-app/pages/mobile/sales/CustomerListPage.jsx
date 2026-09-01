import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import CustomerForm from '../../../components/sales/customer/CustomerForm';
import useCustomerList from '../../../components/sales/customer/useCustomerList';
import { money } from '../../../components/sales/order/statuses';

export default function CustomerListPage() {
    const c = useCustomerList();

    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">Customers</h1>
                <p className="page-subtitle">Manage your customer directory</p>
            </div>

            <button
                type="button" onClick={c.openCreate} className="btn-primary"
                style={{ width: '100%', justifyContent: 'center', marginBottom: 14 }}
            >
                + Add Customer
            </button>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={c.query}
                    onChange={(e) => c.setQuery(e.target.value)}
                    placeholder="Search name, contact, email, phone…"
                    aria-label="Search customers"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {c.query ? `${c.filtered.length} of ${c.customers.length} customers` : `${c.customers.length} customers`}
                </div>
            </div>

            {c.filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {c.query ? 'No customers match that search.' : 'No customers found.'}
                </p>
            )}

            {c.filtered.map((customer) => (
                <div key={customer.id} style={{
                    background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
                    padding: 12, marginBottom: 8,
                }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontWeight: 600, color: '#0f172a', fontSize: 14 }}>{customer.name}</div>
                            {customer.contact_person && (
                                <div style={{ fontSize: 13, color: '#64748b' }}>{customer.contact_person}</div>
                            )}
                        </div>
                        <span className={customer.is_active ? 'badge-green' : 'badge-gray'} style={{ flexShrink: 0 }}>
                            {customer.is_active ? 'Active' : 'Inactive'}
                        </span>
                    </div>

                    {(customer.email || customer.phone) && (
                        <div style={{ fontSize: 12.5, color: '#64748b', marginTop: 6 }}>
                            {[customer.email, customer.phone].filter(Boolean).join(' · ')}
                        </div>
                    )}

                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12.5, marginTop: 6, gap: 8 }}>
                        <span style={{ color: '#94a3b8' }}>Limit {money(customer.credit_limit)}</span>
                        {/* Money owed is the number worth colouring. */}
                        <span className={Number(customer.outstanding_balance ?? 0) > 0 ? 'text-red-600 font-semibold' : 'text-gray-500'}>
                            {money(customer.outstanding_balance)}
                        </span>
                    </div>

                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 10 }}>
                        <button type="button" onClick={() => c.openEdit(customer)} className="btn-secondary btn-sm">Edit</button>
                        <button type="button" onClick={() => c.setDeleting(customer)} className="btn-danger btn-sm">Delete</button>
                    </div>
                </div>
            ))}

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
