import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import PurchaseOrderForm from '../../../components/purchase/order/PurchaseOrderForm';
import { STATUS_LABELS, badgeClassFor, formatDate, money } from '../../../components/purchase/order/statuses';
import usePurchaseOrderList from '../../../components/purchase/order/usePurchaseOrderList';

export default function PurchaseOrderListPage() {
    const o = usePurchaseOrderList();

    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">Purchase Orders</h1>
                <p className="page-subtitle">Manage all purchase orders</p>
            </div>

            <button type="button" onClick={o.openCreate} className="btn-primary" style={{ width: '100%', justifyContent: 'center', marginBottom: 14 }}>
                + New PO
            </button>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={o.query}
                    onChange={(e) => o.setQuery(e.target.value)}
                    placeholder="Search PO number, supplier, status…"
                    aria-label="Search purchase orders"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {o.query ? `${o.filtered.length} of ${o.orders.length} orders` : `${o.orders.length} orders`}
                </div>
            </div>

            {o.filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {o.query ? 'No purchase orders match that search.' : 'No purchase orders found.'}
                </p>
            )}

            {/* A seven-column table does not fit a phone, so each order is a card
                carrying the same fields. */}
            {o.filtered.map((order) => (
                <div key={order.id} style={{
                    background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
                    padding: 12, marginBottom: 8,
                }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <a href={`/app/purchase/orders/${order.id}`} className="font-mono" style={{ fontWeight: 600, color: '#2563eb', textDecoration: 'none' }}>
                            {order.po_number}
                        </a>
                        <span style={{ fontWeight: 700, color: '#1f2937' }}>{money(order.total_amount)}</span>
                    </div>
                    <div style={{ fontSize: 13, color: '#64748b', marginTop: 2 }}>{order.supplier_name ?? '—'}</div>
                    <div style={{ fontSize: 12, color: '#94a3b8' }}>
                        {formatDate(order.po_date)}
                        {order.expected_delivery_date && ` · expected ${formatDate(order.expected_delivery_date)}`}
                    </div>
                    <div style={{ marginTop: 6 }}>
                        <span className={badgeClassFor(order.status)}>
                            {STATUS_LABELS[order.status] ?? order.status}
                        </span>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 10 }}>
                        <button type="button" onClick={() => o.openEdit(order)} className="btn-secondary btn-sm">Edit</button>
                        <button type="button" onClick={() => o.setDeleting(order)} className="btn-danger btn-sm">Delete</button>
                    </div>
                </div>
            ))}

            <Modal
                open={o.modalOpen}
                title={o.editing ? `Edit ${o.editing.po_number}` : 'New Purchase Order'}
                onClose={() => o.setModalOpen(false)}
            >
                <PurchaseOrderForm order={o.editing} onSaved={o.handleSaved} onCancel={() => o.setModalOpen(false)} />
            </Modal>
            <ConfirmModal
                open={!!o.deleting}
                title="Delete this purchase order?"
                body={o.deleting ? `${o.deleting.po_number} will be permanently removed.` : ''}
                onConfirm={o.handleDeleteConfirmed}
                onCancel={() => o.setDeleting(null)}
            />
        </div>
    );
}
