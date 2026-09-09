import { Link } from 'react-router-dom';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import SalesOrderForm from '../../../components/sales/order/SalesOrderForm';
import useSalesOrderList from '../../../components/sales/order/useSalesOrderList';
import { badgeClassFor, formatDate, money, statusLabel } from '../../../components/sales/order/statuses';

export default function SalesOrderListPage() {
    const s = useSalesOrderList();

    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">Sales Orders</h1>
                <p className="page-subtitle">Manage customer orders</p>
            </div>

            <button
                type="button" onClick={s.openCreate} className="btn-primary"
                style={{ width: '100%', justifyContent: 'center', marginBottom: 14 }}
            >
                + New Order
            </button>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={s.query}
                    onChange={(e) => s.setQuery(e.target.value)}
                    placeholder="Search order #, customer, status…"
                    aria-label="Search sales orders"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {s.query ? `${s.filtered.length} of ${s.orders.length} orders` : `${s.orders.length} orders`}
                </div>
            </div>

            {s.filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {s.query ? 'No sales orders match that search.' : 'No sales orders found.'}
                </p>
            )}

            {s.filtered.map((order) => (
                <div key={order.id} style={{
                    background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
                    padding: 12, marginBottom: 8,
                }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontWeight: 600, color: '#0f172a', fontSize: 14 }}>{order.customer_name ?? ''}</div>
                            <div className="font-mono" style={{ fontSize: 11, color: '#94a3b8' }}>{order.order_number}</div>
                        </div>
                        <span className={badgeClassFor(order.status)} style={{ flexShrink: 0 }}>{statusLabel(order.status)}</span>
                    </div>

                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12.5, color: '#64748b', marginTop: 6 }}>
                        <span>{formatDate(order.order_date)}</span>
                        <span className="font-medium text-gray-800">{money(order.total_amount)}</span>
                    </div>

                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 10, flexWrap: 'wrap' }}>
                        <Link to={`/app/sales/orders/${order.id}`} className="btn-primary btn-sm">View</Link>
                        {order.status === 'draft' && (
                            <button type="button" onClick={() => s.setConfirming(order)} className="btn-primary btn-sm">Confirm</button>
                        )}
                        {order.status === 'draft' && (
                            <button type="button" onClick={() => s.openEdit(order)} className="btn-secondary btn-sm">Edit</button>
                        )}
                        {order.status === 'draft' && (
                            <button type="button" onClick={() => s.setDeleting(order)} className="btn-danger btn-sm">Delete</button>
                        )}
                    </div>
                </div>
            ))}

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
