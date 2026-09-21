import { describe, it, expect } from 'vitest';
import { liveOrders, orderLabel } from './purchaseOrders';

const request = {
    purchase_orders: [
        { id: 29, po_number: 'PO-00029', supplier_name: 'Yousif Dhneem', status: 'cancelled' },
        { id: 30, po_number: 'PO-00030', supplier_name: 'Yousif Dhneem', status: 'sent' },
    ],
};

describe('liveOrders', () => {
    /**
     * The reported symptom: two download buttons side by side, both reading
     * "Yousif Dhneem". Re-issuing cancels the stale LPO and creates a new one,
     * and the request keeps both.
     */
    it('drops a superseded LPO so a re-issued request shows one', () => {
        expect(liveOrders(request).map((po) => po.po_number)).toEqual(['PO-00030']);
    });

    it('keeps received orders — they happened', () => {
        const orders = liveOrders({ purchase_orders: [{ id: 1, status: 'received' }, { id: 2, status: 'sent' }] });
        expect(orders).toHaveLength(2);
    });

    it('copes with a request whose orders have not loaded', () => {
        expect(liveOrders(undefined)).toEqual([]);
        expect(liveOrders({})).toEqual([]);
    });
});

describe('orderLabel', () => {
    // Two live orders to one supplier are indistinguishable by name alone.
    it('names the supplier and the order', () => {
        expect(orderLabel({ supplier_name: 'Yousif Dhneem', po_number: 'PO-00030' }))
            .toBe('Yousif Dhneem (PO-00030)');
    });

    it('falls back when a supplier or number is missing', () => {
        expect(orderLabel({ po_number: 'PO-00030' })).toBe('LPO (PO-00030)');
        expect(orderLabel({ supplier_name: 'Yousif Dhneem' })).toBe('Yousif Dhneem');
    });
});
