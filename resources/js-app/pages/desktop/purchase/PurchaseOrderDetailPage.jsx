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

    // Print/PDF stay server-rendered (DomPDF), so these are real navigations
    // rather than router links.
    return (
        <div>
            <div style={{
                marginBottom: 16, display: 'flex', alignItems: 'flex-start',
                justifyContent: 'space-between', flexWrap: 'wrap', gap: 12,
            }}>
                <div>
                    <Link to="/app/purchase/orders" className="text-sm text-blue-600 hover:text-blue-800">
                        ← Back to Purchase Orders
                    </Link>
                    <p style={{ fontSize: 13, margin: '6px 0 0' }}>
                        <Link to="/app/purchase/orders" style={{ color: '#2563eb' }}>Purchase Orders</Link>
                        <span style={{ color: '#94a3b8' }}> / {order?.po_number ?? '…'}</span>
                    </p>
                </div>
                {order && (
                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                        <a href={`/purchase/orders/${order.id}/print`} target="_blank" rel="noreferrer"
                           className="px-4 py-2 rounded-md bg-white hover:bg-gray-50 text-gray-800 border border-gray-300 text-sm font-medium">
                            Print LPO
                        </a>
                        <a href={`/purchase/orders/${order.id}/pdf`}
                           className="px-4 py-2 rounded-md bg-white hover:bg-gray-50 text-gray-800 border border-gray-300 text-sm font-medium">
                            Download PDF
                        </a>
                        <Link to={`/app/purchase/grns?purchase_order_id=${order.id}`}
                              className="px-4 py-2 rounded-md bg-green-600 hover:bg-green-700 text-white text-sm font-medium">
                            Create GRN
                        </Link>
                    </div>
                )}
            </div>

            {loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}
            {!loading && !order && <p style={{ fontSize: 14, color: '#64748b' }}>That purchase order could not be found.</p>}
            <PurchaseOrderDetail order={order} />
        </div>
    );
}
