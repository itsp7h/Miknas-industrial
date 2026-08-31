import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import PurchaseOrderDetail from '../../../components/purchase/order/PurchaseOrderDetail';
import { apiGet } from '../../../api/client';
import { useToast } from '../../../components/ui/Toast';

export default function PurchaseOrderDetailPage() {
    const { id } = useParams();
    const [order, setOrder] = useState(null);
    const [loading, setLoading] = useState(true);
    const { showToast } = useToast();

    useEffect(() => {
        apiGet(`/purchase/orders/${id}`)
            .then((response) => setOrder(response.data))
            .catch(() => showToast('Failed to load that purchase order.', 'error'))
            .finally(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <Link to="/app/purchase/orders" className="text-sm text-blue-600">← Purchase orders</Link>
            </div>

            {loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}
            {!loading && !order && <p style={{ fontSize: 14, color: '#64748b' }}>That purchase order could not be found.</p>}

            <PurchaseOrderDetail order={order} compact />

            {/* Full-width, thumb-reachable actions below the sheet rather than a
                cramped top-right button row. Print/PDF stay server-rendered. */}
            {order && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 8, marginTop: 16 }}>
                    <a href={`/purchase/grns/create?purchase_order_id=${order.id}`}
                       style={{ textAlign: 'center' }}
                       className="px-4 py-3 rounded-md bg-green-600 text-white text-sm font-medium">
                        Create GRN
                    </a>
                    <a href={`/purchase/orders/${order.id}/pdf`}
                       style={{ textAlign: 'center' }}
                       className="px-4 py-3 rounded-md bg-white text-gray-800 border border-gray-300 text-sm font-medium">
                        Download PDF
                    </a>
                    <a href={`/purchase/orders/${order.id}/print`} target="_blank" rel="noreferrer"
                       style={{ textAlign: 'center' }}
                       className="px-4 py-3 rounded-md bg-white text-gray-800 border border-gray-300 text-sm font-medium">
                        Print LPO
                    </a>
                </div>
            )}
        </div>
    );
}
