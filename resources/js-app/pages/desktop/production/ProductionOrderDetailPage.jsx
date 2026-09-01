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
            <div className="page-header">
                <div>
                    <h1 className="page-title">Production Order</h1>
                    <p className="page-subtitle">
                        <Link to="/app/production/orders" className="text-blue-600 hover:underline">Production Orders</Link>
                        {' / '}{p.order?.order_number ?? '…'}
                    </p>
                </div>
                <div className="flex gap-2">
                    {p.order?.status === 'planned' && (
                        <button type="button" onClick={() => p.setStarting(true)} className="btn-primary">Start Production</button>
                    )}
                    {p.order?.status === 'in_progress' && (
                        <button type="button" onClick={() => p.setCompleting(true)} className="btn-success">Mark Complete</button>
                    )}
                </div>
            </div>

            {p.loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}
            {!p.loading && !p.order && <p style={{ fontSize: 14, color: '#64748b' }}>That production order could not be found.</p>}

            <ProductionOrderDetail order={p.order} />

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
