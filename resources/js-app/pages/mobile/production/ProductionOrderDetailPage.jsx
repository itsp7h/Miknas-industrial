import { Link, useParams } from 'react-router-dom';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import ProductionOrderDetail from '../../../components/production/order/ProductionOrderDetail';
import useProductionOrderDetail from '../../../components/production/order/useProductionOrderDetail';
import { useSetPageTitle } from '../../../layouts/PageTitleContext';

export default function ProductionOrderDetailPage() {
    const { id } = useParams();
    const p = useProductionOrderDetail(id);

    useSetPageTitle(p.order ? `Production Order — ${p.order.order_number}` : null);

    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <Link to="/app/production/orders" style={{ fontSize: 13, color: '#2563eb', textDecoration: 'none' }}>
                    ← Production Orders
                </Link>
            </div>

            {p.loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}
            {!p.loading && !p.order && <p style={{ fontSize: 14, color: '#64748b' }}>That production order could not be found.</p>}

            <ProductionOrderDetail order={p.order} compact />

            {/* Full-width and thumb-reachable rather than a top-right button. */}
            {p.order?.status === 'planned' && (
                <button
                    type="button" onClick={() => p.setStarting(true)} className="btn-primary"
                    style={{ width: '100%', justifyContent: 'center', marginTop: 16 }}
                >
                    Start Production
                </button>
            )}
            {p.order?.status === 'in_progress' && (
                <button
                    type="button" onClick={() => p.setCompleting(true)} className="btn-success"
                    style={{ width: '100%', justifyContent: 'center', marginTop: 16 }}
                >
                    Mark Complete
                </button>
            )}

            <ConfirmModal
                open={p.starting}
                title="Start this order?"
                body={p.order ? `${p.order.order_number} moves to In Progress and can no longer be edited or deleted.` : ''}
                onConfirm={p.start}
                onCancel={() => p.setStarting(false)}
            />
            <ConfirmModal
                open={p.completing}
                title="Mark this order complete?"
                body={p.order ? `${p.order.order_number} will be marked complete and the production managers are notified. It cannot be reopened.` : ''}
                onConfirm={p.complete}
                onCancel={() => p.setCompleting(false)}
            />
        </div>
    );
}
