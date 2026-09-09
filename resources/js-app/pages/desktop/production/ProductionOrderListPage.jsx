import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import ProductionOrderForm from '../../../components/production/ProductionOrderForm';
import ProductionOrderTable from '../../../components/production/order/ProductionOrderTable';
import useProductionOrderList from '../../../components/production/order/useProductionOrderList';

export default function ProductionOrderListPage() {
    const p = useProductionOrderList();

    return (
        <div>
            <div className="page-header">
                <div>
                    <h1 className="page-title">Production Orders</h1>
                    <p className="page-subtitle">Manage manufacturing orders</p>
                </div>
                <button type="button" onClick={p.openCreate} className="btn-primary">+ New Order</button>
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
                        value={p.query}
                        onChange={(e) => p.setQuery(e.target.value)}
                        placeholder="Search order #, product, status…"
                        aria-label="Search production orders"
                        autoComplete="off"
                        style={{ padding: '8px 14px 8px 34px', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13.5, width: 340, outline: 'none' }}
                    />
                </div>
                <div style={{ fontSize: 12.5, color: '#94a3b8', whiteSpace: 'nowrap' }}>
                    {p.query ? `${p.filtered.length} of ${p.orders.length} orders` : `${p.orders.length} orders`}
                </div>
            </div>

            <ProductionOrderTable
                orders={p.filtered}
                onEdit={p.openEdit}
                onStart={p.setStarting}
                onComplete={p.setCompleting}
                onDelete={p.setDeleting}
            />

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
