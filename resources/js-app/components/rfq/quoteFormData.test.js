import { describe, it, expect } from 'vitest';
import { quoteFormData } from './useRfqPortal';

/**
 * With a file attached the quote travels as multipart, where everything is a
 * string. The API's `accepted` and `boolean` rules take "1" / "0", and a
 * field left out is null to it, so that is how this encodes.
 */
describe('quoteFormData', () => {
    it('flattens the quote the way the API reads multipart', () => {
        const file = new File(['%PDF'], 'q.pdf', { type: 'application/pdf' });
        const form = quoteFormData({
            terms: true,
            reference: 'Q-1',
            lead_time_days: null,
            items: [
                { id: 7, unit_price: 2, is_vatable: false, not_available: false, supplier_description: null },
                { id: 9, unit_price: null, is_vatable: true, not_available: true, supplier_unit: 'BAG', supplier_quantity: 4 },
            ],
        }, file);

        expect(form.get('terms')).toBe('1');
        expect(form.get('reference')).toBe('Q-1');
        expect(form.has('lead_time_days')).toBe(false);
        expect(form.get('items[0][id]')).toBe('7');
        expect(form.get('items[0][is_vatable]')).toBe('0');
        expect(form.has('items[0][supplier_description]')).toBe(false);
        expect(form.has('items[1][unit_price]')).toBe(false);
        expect(form.get('items[1][not_available]')).toBe('1');
        expect(form.get('items[1][supplier_unit]')).toBe('BAG');
        expect(form.get('items[1][supplier_quantity]')).toBe('4');
        expect(form.get('document')).toBeInstanceOf(File);
        expect(form.get('document').name).toBe('q.pdf');
    });
});
