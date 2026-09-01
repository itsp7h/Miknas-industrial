import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import Modal from '../../../components/ui/Modal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import OrderDetail from '../../../components/sales/order/OrderDetail';
import SalesOrderForm from '../../../components/sales/order/SalesOrderForm';
import useSalesOrderDetail from '../../../components/sales/order/useSalesOrderDetail';
import { useSetPageTitle } from '../../../layouts/PageTitleContext';

export default function SalesOrderDetailPage() {
    const { id } = useParams();
    const s = useSalesOrderDetail(id);
    const [editing, setEditing] = useState(false);

    useSetPageTitle(s.order ? `Sales Order — ${s.order.order_number}` : null);

    return (
        <div>
            <div className="page-header">
                <div>
                    <h1 className="page-title">Sales Order</h1>
                    <p className="page-subtitle">
                        <Link to="/app/sales/orders" className="text-blue-600 hover:underline">Sales Orders</Link>
                        {' / '}{s.order?.order_number ?? '…'}
                    </p>
                </div>
                {/* Blade's header actions, which the port had dropped entirely —
                    there was no way to confirm or edit from this page. */}
                <div className="flex gap-2">
                    {s.order?.status === 'draft' && (
                        <button type="button" onClick={() => s.setConfirming(true)} className="btn-primary">Confirm Order</button>
                    )}
                    {s.order?.status === 'confirmed' && (
                        <Link to={`/app/sales/delivery-notes?sales_order_id=${s.order.id}`} className="btn-primary">
                            Create Delivery Note
                        </Link>
                    )}
                    {s.order?.status === 'draft' && (
                        <button type="button" onClick={() => setEditing(true)} className="btn-secondary">Edit</button>
                    )}
                </div>
            </div>

            {s.loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}
            {!s.loading && !s.order && <p style={{ fontSize: 14, color: '#64748b' }}>That sales order could not be found.</p>}

            <OrderDetail order={s.order} />

            <Modal open={editing} title={`Edit ${s.order?.order_number ?? ''}`} onClose={() => setEditing(false)}>
                <SalesOrderForm
                    order={s.order}
                    onSaved={() => { setEditing(false); s.reload(); }}
                    onCancel={() => setEditing(false)}
                />
            </Modal>
            <ConfirmModal
                open={s.confirming}
                title="Confirm this order?"
                body={s.order
                    ? `${s.order.order_number} will be confirmed and can no longer be edited. The customer is notified if they have a WhatsApp number.`
                    : ''}
                onConfirm={s.confirm}
                onCancel={() => s.setConfirming(false)}
            />
        </div>
    );
}
