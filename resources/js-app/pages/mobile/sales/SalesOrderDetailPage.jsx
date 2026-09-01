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
            <div style={{ marginBottom: 12 }}>
                <Link to="/app/sales/orders" style={{ fontSize: 13, color: '#2563eb', textDecoration: 'none' }}>
                    ← Sales Orders
                </Link>
            </div>

            {s.loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}
            {!s.loading && !s.order && <p style={{ fontSize: 14, color: '#64748b' }}>That sales order could not be found.</p>}

            <OrderDetail order={s.order} compact />

            {/* Full-width and thumb-reachable rather than a top-right row. */}
            {s.order?.status === 'draft' && (
                <>
                    <button
                        type="button" onClick={() => s.setConfirming(true)} className="btn-primary"
                        style={{ width: '100%', justifyContent: 'center', marginTop: 16 }}
                    >
                        Confirm Order
                    </button>
                    <button
                        type="button" onClick={() => setEditing(true)} className="btn-secondary"
                        style={{ width: '100%', justifyContent: 'center', marginTop: 8 }}
                    >
                        Edit
                    </button>
                </>
            )}
            {s.order?.status === 'confirmed' && (
                <Link
                    to={`/app/sales/delivery-notes?sales_order_id=${s.order.id}`} className="btn-primary"
                    style={{ display: 'flex', width: '100%', justifyContent: 'center', marginTop: 16 }}
                >
                    Create Delivery Note
                </Link>
            )}

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
