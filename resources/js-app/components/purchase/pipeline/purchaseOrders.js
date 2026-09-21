/**
 * The LPOs on a request that are still in force.
 *
 * Re-issuing does not replace an LPO in place: LpoGenerationService cancels the
 * stale orders and creates new ones, so the request keeps every attempt. That
 * is the right record to keep — the sidebar lists them all with their status
 * pills, which is the audit trail — but anywhere that offers an LPO to *act
 * on*, a cancelled one is noise at best. It showed up as a row of identical
 * download buttons, one per superseded order, all labelled with the same
 * supplier name.
 */
export function liveOrders(request) {
    return (request?.purchase_orders ?? []).filter((po) => po.status !== 'cancelled');
}

/** "Yousif Dhneem (PO-00030)" — the name alone does not identify an order. */
export function orderLabel(po) {
    const name = po.supplier_name ?? 'LPO';

    return po.po_number ? `${name} (${po.po_number})` : name;
}
