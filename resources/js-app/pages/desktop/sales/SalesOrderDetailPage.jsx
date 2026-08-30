import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import Card from '../../../components/ui/Card';
import OrderDetail from '../../../components/sales/order/OrderDetail';
import { apiGet } from '../../../api/client';
import { useToast } from '../../../components/ui/Toast';

export default function SalesOrderDetailPage() {
    const { id } = useParams();
    const [order, setOrder] = useState(null);
    const [loading, setLoading] = useState(true);
    const { showToast } = useToast();

    useEffect(() => {
        apiGet(`/sales/orders/${id}`)
            .then((response) => setOrder(response.data))
            .catch(() => showToast('Failed to load that sales order.', 'error'))
            .finally(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    return (
        <Card title="Sales Order">
            <div style={{ marginBottom: 12 }}>
                <Link to="/app/sales/orders" className="text-sm text-blue-600 hover:text-blue-800">← Back to sales orders</Link>
            </div>
            {loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}
            {!loading && !order && <p style={{ fontSize: 14, color: '#64748b' }}>That sales order could not be found.</p>}
            <OrderDetail order={order} />
        </Card>
    );
}
