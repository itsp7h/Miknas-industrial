import { describe, it, expect } from 'vitest';
import { goodsReceipts, receiptCaption } from './goodsReceipts';

const withGrns = (...grns) => ({ goods_receipt_notes: grns });
const draft = (id) => ({ id, grn_number: `GRN-0000${id}`, status: 'draft' });
const confirmed = (id) => ({ id, grn_number: `GRN-0000${id}`, status: 'confirmed' });

describe('goodsReceipts', () => {
    it('separates what moved stock from what only looks like it did', () => {
        const { all, drafts, confirmed: done } = goodsReceipts(withGrns(draft(1), confirmed(2)));

        expect(all).toHaveLength(2);
        expect(drafts.map((g) => g.id)).toEqual([1]);
        expect(done.map((g) => g.id)).toEqual([2]);
    });

    it('copes with a request that carries none', () => {
        expect(goodsReceipts(undefined).all).toEqual([]);
        expect(goodsReceipts({}).drafts).toEqual([]);
    });
});

describe('receiptCaption', () => {
    /**
     * The reported symptom: a GRN was recorded and the Receiving step looked
     * untouched. A draft has moved no stock, and the line has to say so rather
     * than counting it as received.
     */
    it('calls out a recorded GRN that is still waiting to be confirmed', () => {
        expect(receiptCaption(withGrns(draft(1)))).toBe('1 recorded, not yet confirmed');
    });

    it('counts confirmed receipts as received into stock', () => {
        expect(receiptCaption(withGrns(confirmed(1), confirmed(2))))
            .toBe('2 GRN(s) received into stock');
    });

    it('reports both when some are confirmed and some are not', () => {
        expect(receiptCaption(withGrns(confirmed(1), draft(2))))
            .toBe('1 GRN(s) received into stock · 1 recorded, not yet confirmed');
    });

    it('says nothing when nothing has been recorded', () => {
        expect(receiptCaption(withGrns())).toBe('');
    });
});
