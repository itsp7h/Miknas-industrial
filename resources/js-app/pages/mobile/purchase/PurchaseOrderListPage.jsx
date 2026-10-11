import { useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import PurchaseOrderModal from '../../../components/purchase/order/PurchaseOrderModal';
import { STATUS_LABELS, money } from '../../../components/purchase/order/statuses';
import usePurchaseOrderList from '../../../components/purchase/order/usePurchaseOrderList';
import { DocRow, PurchasingHeader } from '../../../components/purchase/mobile/Purchasing';
import { Card, CountLine, EmptyState, MobilePage, SearchField } from '../../../components/mobile/ui';
import { longDate } from '../../../components/mobile/format';
import { useAccess } from '../../../layouts/AccessContext';

const TONE = { draft: 'slate', sent: 'blue', received: 'green', cancelled: 'red' };

// Purchasing → Orders (SteelERP-Mobile-Designs-V2). A row opens the order;
// editing and deleting are on the order's own page.
export default function PurchaseOrderListPage() {
    const o = usePurchaseOrderList();
    const canCreate = useAccess().can('purchase-orders.create');
    const [params, setParams] = useSearchParams();

    // ?new=1 is Home's "New LPO" quick action.
    useEffect(() => {
        if (params.get('new') !== '1') return;
        if (canCreate) o.openCreate();
        params.delete('new');
        setParams(params, { replace: true });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [params]);

    return (
        <MobilePage gap={14}>
            <PurchasingHeader
                tab="orders"
                action={{
                    label: 'New purchase order', short: 'LPO', onClick: o.openCreate, allowed: canCreate,
                    denied: 'You do not have permission to create purchase orders',
                }}
            />

            <SearchField value={o.query} onChange={o.setQuery} placeholder="Search orders" />
            <CountLine>
                {o.query
                    ? `${o.filtered.length} of ${o.orders.length} purchase orders`
                    : `${o.orders.length} purchase order${o.orders.length === 1 ? '' : 's'}`}
            </CountLine>

            {o.filtered.length === 0 ? (
                <EmptyState icon="clipboard" title={o.query ? 'No purchase orders match that search' : 'No purchase orders yet'} />
            ) : (
                <Card>
                    {o.filtered.map((order, i) => (
                        <DocRow
                            key={order.id}
                            to={`/app/purchase/orders/${order.id}`}
                            number={order.po_number}
                            title={order.supplier_name ?? '—'}
                            sub={`${longDate(order.po_date)} · ${order.expected_delivery_date ? `delivery ${longDate(order.expected_delivery_date)}` : 'delivery not set'}`}
                            amount={money(order.total_amount)}
                            status={STATUS_LABELS[order.status] ?? order.status}
                            statusTone={TONE[order.status] ?? 'slate'}
                            last={i === o.filtered.length - 1}
                        />
                    ))}
                </Card>
            )}

            {o.modalOpen && (
                <PurchaseOrderModal
                    order={o.editing}
                    onSaved={o.handleSaved}
                    onCancel={() => o.setModalOpen(false)}
                />
            )}
        </MobilePage>
    );
}
