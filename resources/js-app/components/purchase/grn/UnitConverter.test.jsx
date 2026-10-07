import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import GrnDetail from './GrnDetail';
import { confirmBlockedReason, needsLabel } from './grnStyles';

/**
 * Cement is kept in KG; the supplier sent 5 BAG at 12.000 a bag and never
 * said what a bag holds. The GRN page is where that is set, before Confirm.
 */
const pending = {
    id: 31, item_id: 9, item_name: 'Cement', unit_of_measure: 'KG',
    quantity_received: '0.000', type: 'inventory',
    supplier_unit: 'BAG', supplier_quantity: 5, supplier_quantity_ordered: 5, supplier_rate: 12,
    unit_factor: null, conversion_pending: true, suggested_factor: 25,
};

const grnWith = (line, overrides = {}) => ({
    id: 7, grn_number: 'GRN-00007', purchase_order_id: 3, po_number: 'PO-00003',
    supplier_name: 'Gulf Cement', warehouse_name: 'Main Store', received_date: '2026-10-07',
    status: 'draft', items: [line], documents: null, missing_documents: [],
    missing_conversions: line.conversion_pending ? ['Cement'] : [],
    ...overrides,
});

const mount = (grn, props = {}) => render(
    <MemoryRouter><GrnDetail grn={grn} {...props} /></MemoryRouter>,
);

describe('GRN unit conversion', () => {
    it('blocks Confirm and badges the receipt while a line is unconverted', () => {
        const grn = grnWith(pending);

        expect(confirmBlockedReason(grn)).toBe('Set the unit conversion for Cement to confirm this GRN.');
        expect(needsLabel(grn)).toBe('Needs conversion');
        expect(needsLabel({ ...grn, missing_documents: ['Tax Invoice'] })).toBe('Needs Tax Invoice & conversion');
        // Documents are asked for first: they are what the store chases.
        expect(confirmBlockedReason({ ...grn, missing_documents: ['GRN'] })).toBe('Upload the GRN to confirm this GRN.');
    });

    it('starts from the suggested factor and previews what will be stocked, at what cost', () => {
        mount(grnWith(pending), { canConvert: true, onConvert: vi.fn() });

        // Ordered 5 BAG, counted 5 BAG, and nothing in KG yet.
        expect(screen.getAllByText('5.00 BAG', { selector: 'td' })).toHaveLength(2);
        expect(screen.getByText('→ KG not set yet')).toBeInTheDocument();
        expect(screen.getByLabelText('How many KG one BAG holds, for Cement')).toHaveValue(25);
        expect(screen.getByText('(suggested)')).toBeInTheDocument();
        // 5 × 25 = 125 KG at 12 / 25 = 0.480 a KG.
        expect(screen.getByText('125 KG')).toBeInTheDocument();
        expect(screen.getByText(/at 0\.480 \/ KG/)).toBeInTheDocument();
    });

    it('saves the factor the user settles on', async () => {
        const onConvert = vi.fn().mockResolvedValue(true);
        mount(grnWith(pending), { canConvert: true, onConvert });

        fireEvent.change(screen.getByLabelText('How many KG one BAG holds, for Cement'), { target: { value: '50' } });
        expect(screen.getByText('250 KG')).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Set conversion' }));

        await waitFor(() => expect(onConvert).toHaveBeenCalledWith(31, 50));
    });

    it('shows the converter disabled, with the reason, to someone without the permission', () => {
        const onConvert = vi.fn();
        mount(grnWith(pending), { canConvert: false, onConvert });

        const field = screen.getByLabelText('How many KG one BAG holds, for Cement');
        const button = screen.getByRole('button', { name: 'Set conversion' });
        expect(field).toBeDisabled();
        expect(button).toBeDisabled();
        expect(button).toHaveAttribute('title', 'You do not have permission to set unit conversions');

        fireEvent.click(button);
        expect(onConvert).not.toHaveBeenCalled();
    });

    it('will not save an empty or zero factor', () => {
        mount(grnWith({ ...pending, suggested_factor: null }), { canConvert: true, onConvert: vi.fn() });

        const button = screen.getByRole('button', { name: 'Set conversion' });
        expect(screen.getByLabelText('How many KG one BAG holds, for Cement')).toHaveValue(null);
        expect(button).toBeDisabled();
        expect(button).toHaveAttribute('title', 'Enter how many KG one BAG holds');
    });

    it('a converted line shows ours, and Update only once the figure changes', () => {
        const converted = {
            ...pending, unit_factor: 25, conversion_pending: false, suggested_factor: undefined,
            quantity_received: '125.000', converted_by_name: 'Store Keeper',
        };
        mount(grnWith(converted), { canConvert: true, onConvert: vi.fn() });

        expect(screen.getByText('= 125.00 KG')).toBeInTheDocument();
        const update = screen.getByRole('button', { name: 'Update' });
        expect(update).toBeDisabled();

        fireEvent.change(screen.getByLabelText('How many KG one BAG holds, for Cement'), { target: { value: '20' } });
        expect(update).not.toBeDisabled();
    });

    it('a confirmed GRN shows the conversion it was stocked on, read-only', () => {
        const converted = { ...pending, unit_factor: 25, conversion_pending: false, quantity_received: '125.000', converted_by_name: 'Store Keeper' };
        mount(grnWith(converted, { status: 'confirmed' }), { canConvert: true, onConvert: vi.fn() });

        expect(screen.queryByLabelText('How many KG one BAG holds, for Cement')).not.toBeInTheDocument();
        expect(screen.getByText('25 KG')).toBeInTheDocument();
        expect(screen.getByText(/set by Store Keeper/)).toBeInTheDocument();
    });

    it('a line in our own unit has no converter', () => {
        mount(grnWith({ id: 1, item_name: 'Steel Plate', quantity_ordered: '10.00', quantity_received: '4.000', type: 'inventory' }), { canConvert: true, onConvert: vi.fn() });

        expect(screen.queryByText(/Conversion/)).not.toBeInTheDocument();
    });
});
