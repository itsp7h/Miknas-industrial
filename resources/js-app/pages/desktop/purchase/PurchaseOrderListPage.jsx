import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import PurchaseOrderForm from '../../../components/purchase/order/PurchaseOrderForm';
import PurchaseOrderTable from '../../../components/purchase/order/PurchaseOrderTable';
import usePurchaseOrderList from '../../../components/purchase/order/usePurchaseOrderList';

export default function PurchaseOrderListPage() {
    const o = usePurchaseOrderList();

    return (
        <div>
            <div className="page-header">
                <div>
                    <h1 className="page-title">Purchase Orders</h1>
                    <p className="page-subtitle">Manage all purchase orders</p>
                </div>
                <button type="button" onClick={o.openCreate} className="btn-primary">+ New PO</button>
            </div>

            {/*
              The Blade page paginated server-side and so had no search. This
              list loads in full, which would be unusable without one, so it
              filters client-side per gotcha #6 — styled like the Suppliers
              search bar for consistency.
            */}
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
                        value={o.query}
                        onChange={(e) => o.setQuery(e.target.value)}
                        placeholder="Search PO number, supplier, status…"
                        aria-label="Search purchase orders"
                        autoComplete="off"
                        style={{
                            padding: '8px 14px 8px 34px', border: '1px solid #e2e8f0', borderRadius: 8,
                            fontSize: 13.5, width: 340, outline: 'none',
                        }}
                    />
                </div>
                <div style={{ fontSize: 12.5, color: '#94a3b8', whiteSpace: 'nowrap' }}>
                    {o.query ? `${o.filtered.length} of ${o.orders.length} orders` : `${o.orders.length} orders`}
                </div>
            </div>

            <PurchaseOrderTable orders={o.filtered} onEdit={o.openEdit} onDelete={o.setDeleting} />

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
