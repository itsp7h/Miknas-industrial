import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import PurchaseOrderDetail from '../../../components/purchase/order/PurchaseOrderDetail';
import LpoDeliveryStatus from '../../../components/purchase/order/LpoDeliveryStatus';
import PurchaseOrderModal from '../../../components/purchase/order/PurchaseOrderModal';
import ConfirmModal from '../../../components/ui/ConfirmModal';
import { STATUS_LABELS, money } from '../../../components/purchase/order/statuses';
import { apiDelete, apiGet } from '../../../api/client';
import { useToast } from '../../../components/ui/Toast';
import { useAccess } from '../../../layouts/AccessContext';
import { useSetPageTitle } from '../../../layouts/PageTitleContext';
import {
    ActionSheet, BarButton, BottomBar, EmptyState, Hero, Loading, MobilePage, Pill,
} from '../../../components/mobile/ui';
import { MONO } from '../../../components/mobile/theme';
import { longDate } from '../../../components/mobile/format';

const TONE = { draft: 'slate', sent: 'blue', received: 'green', cancelled: 'red' };

// One purchase order (LPO). Receiving against it is the bottom bar's main
// action; editing, deleting and the PDF are under "⋯".
export default function PurchaseOrderDetailPage() {
    const { id } = useParams();
    const navigate = useNavigate();
    const { can } = useAccess();
    const { showToast } = useToast();
    const [order, setOrder] = useState(null);
    const [loading, setLoading] = useState(true);
    const [menu, setMenu] = useState(false);
    const [editing, setEditing] = useState(false);
    const [deleting, setDeleting] = useState(false);

    useSetPageTitle(order ? `Purchase Order — ${order.po_number}` : null);

    useEffect(() => {
        apiGet(`/purchase/orders/${id}`)
            .then((response) => setOrder(response.data))
            .catch(() => showToast('Failed to load that purchase order.', 'error'))
            .finally(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    async function remove() {
        setDeleting(false);
        try {
            await apiDelete(`/purchase/orders/${order.id}`);
            showToast('Purchase order deleted.', 'success');
            navigate('/app/purchase/orders', { replace: true });
        } catch (err) {
            showToast(err.message || 'Failed to delete the order.', 'error');
        }
    }

    const back = { to: '/app/purchase/orders', label: 'Orders' };

    if (!order) {
        return (
            <MobilePage>
                <Hero zone="purchase" back={back} title={loading ? 'Loading…' : 'Not found'} />
                {loading ? <Loading /> : <EmptyState icon="clipboard" title="That purchase order could not be found." />}
            </MobilePage>
        );
    }

    const canReceive = can('goods-receipts.create') && ['sent', 'partial'].includes(order.status);

    return (
        <MobilePage gap={16}>
            <Hero
                zone="purchase"
                back={back}
                title={order.po_number}
                titleStyle={{ fontFamily: MONO, fontSize: 22, fontWeight: 500 }}
                actions={<BarButton icon="more" label="More options" onClick={() => setMenu(true)} />}
            >
                <div style={{ display: 'flex', flexDirection: 'column', gap: 8, marginTop: -8 }}>
                    <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                        <Pill tone={TONE[order.status] ?? 'slate'}>{STATUS_LABELS[order.status] ?? order.status}</Pill>
                        <span style={{ fontSize: 17, fontWeight: 700 }}>{money(order.total_amount)}</span>
                    </div>
                    <span style={{ fontSize: 14, color: 'rgba(255,255,255,0.85)' }}>
                        {[order.supplier_name, longDate(order.po_date)].filter(Boolean).join(' · ')}
                    </span>
                </div>
            </Hero>

            <LpoDeliveryStatus order={order} onSent={setOrder} compact />
            <PurchaseOrderDetail order={order} />

            <BottomBar>
                {canReceive && (
                    <Link
                        to={`/app/purchase/grns?purchase_order_id=${order.id}`}
                        style={{
                            minHeight: 52, borderRadius: 16, background: '#15803D', color: '#FFFFFF', fontSize: 16,
                            fontWeight: 600, textDecoration: 'none', display: 'flex', alignItems: 'center', justifyContent: 'center',
                        }}
                    >
                        Receive goods
                    </Link>
                )}
            </BottomBar>

            <ActionSheet
                open={menu}
                onClose={() => setMenu(false)}
                title={order.po_number}
                options={[
                    { label: 'Download PDF', href: `/purchase/orders/${order.id}/pdf` },
                    { label: 'Print LPO', href: `/purchase/orders/${order.id}/print`, newTab: true },
                    {
                        label: 'Edit order', onClick: () => setEditing(true),
                        disabled: !can('purchase-orders.edit'), disabledReason: 'You do not have permission to edit purchase orders',
                    },
                    {
                        label: 'Delete order', danger: true, onClick: () => setDeleting(true),
                        disabled: !can('purchase-orders.delete'), disabledReason: 'You do not have permission to delete purchase orders',
                    },
                ]}
            />

            {editing && (
                <PurchaseOrderModal
                    order={order}
                    onSaved={(saved) => { setOrder(saved); setEditing(false); showToast('Purchase order saved.', 'success'); }}
                    onCancel={() => setEditing(false)}
                />
            )}
            <ConfirmModal
                open={deleting}
                title="Delete this purchase order?"
                body={`${order.po_number} will be permanently removed.`}
                onConfirm={remove}
                onCancel={() => setDeleting(false)}
            />
        </MobilePage>
    );
}
