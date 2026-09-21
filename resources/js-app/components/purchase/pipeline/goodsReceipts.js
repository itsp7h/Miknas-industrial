/**
 * What has been received against a request's LPOs, split by whether it counts.
 *
 * Recording a GRN and receiving the goods are two steps: `store` writes a
 * *draft*, and confirming it is what raises stock, advances the LPO's received
 * quantities and — once every LPO on the request is met — moves the request on
 * to Payment. A draft GRN has moved nothing.
 *
 * The pipeline showed neither, so recording one left the Receiving step looking
 * exactly as it had before: the same "Record GRN" button, no sign that anything
 * had happened, and no hint that the thing just recorded was still waiting to
 * be confirmed.
 */
export function goodsReceipts(request) {
    const all = request?.goods_receipt_notes ?? [];

    return {
        all,
        drafts: all.filter((grn) => grn.status === 'draft'),
        confirmed: all.filter((grn) => grn.status === 'confirmed'),
    };
}

/** The Receiving step's one-line summary. '' when nothing is recorded yet. */
export function receiptCaption(request) {
    const { all, drafts, confirmed } = goodsReceipts(request);

    if (!all.length) return '';

    const parts = [];
    if (confirmed.length) parts.push(`${confirmed.length} GRN(s) received into stock`);
    if (drafts.length) parts.push(`${drafts.length} recorded, not yet confirmed`);

    return parts.join(' · ');
}
