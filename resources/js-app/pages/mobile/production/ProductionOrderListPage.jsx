import { Link } from 'react-router-dom';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import ProductionOrderForm from '../../../components/production/ProductionOrderForm';
import useProductionOrderList from '../../../components/production/order/useProductionOrderList';
import { badgeClassFor, formatDate, num, statusLabel } from '../../../components/production/order/orderStyles';

export default function ProductionOrderListPage() {
    const p = useProductionOrderList();

    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">Production Orders</h1>
                <p className="page-subtitle">Manage manufacturing orders</p>
            </div>

            <button
                type="button" onClick={p.openCreate} className="btn-primary"
                style={{ width: '100%', justifyContent: 'center', marginBottom: 14 }}
            >
                + New Order
            </button>

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={p.query}
                    onChange={(e) => p.setQuery(e.target.value)}
                    placeholder="Search order #, product, status…"
                    aria-label="Search production orders"
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {p.query ? `${p.filtered.length} of ${p.orders.length} orders` : `${p.orders.length} orders`}
                </div>
            </div>

            {p.filtered.length === 0 && (
                <p style={{ fontSize: 14, color: '#64748b' }}>
                    {p.query ? 'No production orders match that search.' : 'No production orders found.'}
                </p>
            )}

            {p.filtered.map((order) => (
                <div key={order.id} style={{
                    background: '#fff', border: '1px solid #e2e8f0', borderRadius: 12,
                    padding: 12, marginBottom: 8,
                }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontWeight: 600, color: '#0f172a', fontSize: 14 }}>{order.product_name ?? ''}</div>
                            <div className="font-mono" style={{ fontSize: 11, color: '#94a3b8' }}>{order.order_number}</div>
                        </div>
                        <span className={badgeClassFor(order.status)} style={{ flexShrink: 0 }}>{statusLabel(order.status)}</span>
                    </div>

                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12.5, color: '#64748b', marginTop: 6 }}>
                        <span>{num(order.quantity_produced)} of {num(order.quantity_to_produce)} produced</span>
                        <span>{formatDate(order.production_date)}</span>
                    </div>

                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 10, flexWrap: 'wrap' }}>
                        <Link to={`/app/production/orders/${order.id}`} className="btn-primary btn-sm">View</Link>
                        {order.status === 'planned' && (
                            <button type="button" onClick={() => p.setStarting(order)} className="btn-primary btn-sm">Start</button>
                        )}
                        {order.status === 'in_progress' && (
                            <button type="button" onClick={() => p.setCompleting(order)} className="btn-success btn-sm">Complete</button>
                        )}
                        {order.status === 'planned' && (
                            <button type="button" onClick={() => p.openEdit(order)} className="btn-secondary btn-sm">Edit</button>
                        )}
                        {order.status === 'planned' && (
                            <button type="button" onClick={() => p.setDeleting(order)} className="btn-danger btn-sm">Delete</button>
                        )}
                    </div>
                </div>
            ))}

            <Modal
                open={p.modalOpen}
                title={p.editing ? `Edit ${p.editing.order_number}` : 'New Production Order'}
                onClose={() => p.setModalOpen(false)}
            >
                <ProductionOrderForm order={p.editing} onSaved={p.handleSaved} onCancel={() => p.setModalOpen(false)} />
            </Modal>
            <ConfirmModal
                open={!!p.starting}
                title="Start this order?"
                body={p.starting ? `${p.starting.order_number} moves to In Progress and can no longer be edited or deleted.` : ''}
                onConfirm={p.handleStart}
                onCancel={() => p.setStarting(null)}
            />
            <ConfirmModal
                open={!!p.completing}
                title="Mark this order complete?"
                body={p.completing ? `${p.completing.order_number} will be marked complete and the production managers are notified. It cannot be reopened.` : ''}
                onConfirm={p.handleComplete}
                onCancel={() => p.setCompleting(null)}
            />
            <ConfirmModal
                open={!!p.deleting}
                title="Delete this order?"
                body={p.deleting ? `${p.deleting.order_number} will be permanently removed.` : ''}
                onConfirm={p.handleDeleteConfirmed}
                onCancel={() => p.setDeleting(null)}
            />
        </div>
    );
}
