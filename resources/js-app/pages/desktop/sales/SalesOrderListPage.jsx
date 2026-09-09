import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import SalesOrderForm from '../../../components/sales/order/SalesOrderForm';
import SalesOrderTable from '../../../components/sales/order/SalesOrderTable';
import useSalesOrderList from '../../../components/sales/order/useSalesOrderList';

export default function SalesOrderListPage() {
    const s = useSalesOrderList();

    return (
        <div>
            <div className="page-header">
                <div>
                    <h1 className="page-title">Sales Orders</h1>
                    <p className="page-subtitle">Manage customer orders</p>
                </div>
                <button type="button" onClick={s.openCreate} className="btn-primary">+ New Order</button>
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
                        value={s.query}
                        onChange={(e) => s.setQuery(e.target.value)}
                        placeholder="Search order #, customer, status…"
                        aria-label="Search sales orders"
                        autoComplete="off"
                        style={{ padding: '8px 14px 8px 34px', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13.5, width: 340, outline: 'none' }}
                    />
                </div>
                <div style={{ fontSize: 12.5, color: '#94a3b8', whiteSpace: 'nowrap' }}>
                    {s.query ? `${s.filtered.length} of ${s.orders.length} orders` : `${s.orders.length} orders`}
                </div>
            </div>

            <SalesOrderTable
                orders={s.filtered}
                onEdit={s.openEdit}
                onConfirm={s.setConfirming}
                onDelete={s.setDeleting}
            />

            <Modal
                open={s.modalOpen}
                title={s.editing ? `Edit ${s.editing.order_number}` : 'New Sales Order'}
                onClose={() => s.setModalOpen(false)}
            >
                <SalesOrderForm order={s.editing} onSaved={s.handleSaved} onCancel={() => s.setModalOpen(false)} />
            </Modal>
            <ConfirmModal
                open={!!s.confirming}
                title="Confirm this order?"
                body={s.confirming
                    ? `${s.confirming.order_number} will be confirmed and can no longer be edited. The customer is notified if they have a WhatsApp number.`
                    : ''}
                onConfirm={s.handleConfirm}
                onCancel={() => s.setConfirming(null)}
            />
            <ConfirmModal
                open={!!s.deleting}
                title="Delete this sales order?"
                body={s.deleting ? `${s.deleting.order_number} and its line items will be permanently removed.` : ''}
                onConfirm={s.handleDeleteConfirmed}
                onCancel={() => s.setDeleting(null)}
            />
        </div>
    );
}
