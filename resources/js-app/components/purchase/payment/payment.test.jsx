import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import SupplierPaymentTable from './SupplierPaymentTable';
import { methodLabel, formatDate, money } from './paymentStyles';

const PAYMENTS = [
    {
        id: 1, invoice_number: 'SINV-001', supplier_name: 'Gulf Metals',
        payment_date: '2026-08-20', amount: '400.00',
        payment_method: 'bank_transfer', reference_number: 'TRF-9911',
    },
    {
        id: 2, invoice_number: 'SINV-002', supplier_name: 'Zenith Steel',
        payment_date: '2026-08-22', amount: '75.50',
        payment_method: 'cash', reference_number: null,
    },
];

describe('paymentStyles', () => {
    // The Blade index printed str_replace('_', ' ') under a capitalize class.
    it('labels each enum method readably', () => {
        expect(methodLabel('bank_transfer')).toBe('Bank Transfer');
        expect(methodLabel('cheque')).toBe('Cheque');
        expect(methodLabel('other')).toBe('Other');
    });

    it('falls back to underscore-stripping for an unknown method', () => {
        expect(methodLabel('some_new_method')).toBe('some new method');
    });

    it('formats money and dates the way the Blade page did', () => {
        expect(money('75.5')).toBe('75.50');
        expect(formatDate('2026-09-01')).toBe('01 Sep 2026');
    });
});

describe('SupplierPaymentTable', () => {
    const renderTable = (rows = PAYMENTS, handlers = {}) =>
        render(<SupplierPaymentTable
            payments={rows}
            onEdit={handlers.onEdit ?? (() => {})}
            onDelete={handlers.onDelete ?? (() => {})}
        />);

    /**
     * The Blade table had six columns and no Actions — and its edit/show routes
     * rendered views that did not exist, so a recorded payment could never be
     * corrected. Actions is the seventh.
     */
    it('renders the six Blade columns plus Actions', () => {
        renderTable();
        expect(screen.getAllByRole('columnheader').map((th) => th.textContent)).toEqual([
            'Invoice #', 'Supplier', 'Payment Date', 'Amount', 'Method', 'Reference', 'Actions',
        ]);
    });

    it('shows the readable method label, not the raw enum', () => {
        renderTable();
        expect(screen.getByText('Bank Transfer')).toBeInTheDocument();
        expect(screen.queryByText('bank_transfer')).not.toBeInTheDocument();
    });

    it('shows a dash where no reference was given', () => {
        renderTable([PAYMENTS[1]]);
        expect(screen.getByText('-')).toBeInTheDocument();
    });

    it('right-aligns the amount', () => {
        renderTable([PAYMENTS[0]]);
        expect(screen.getByText('400.00')).toHaveClass('text-right');
    });

    it('calls back with the row on edit and delete', () => {
        const onEdit = vi.fn();
        const onDelete = vi.fn();
        renderTable([PAYMENTS[0]], { onEdit, onDelete });

        fireEvent.click(screen.getByText('Edit'));
        fireEvent.click(screen.getByText('Delete'));

        expect(onEdit).toHaveBeenCalledWith(PAYMENTS[0]);
        expect(onDelete).toHaveBeenCalledWith(PAYMENTS[0]);
    });

    it('says so when nothing has been recorded', () => {
        renderTable([]);
        expect(screen.getByText('No payments recorded.')).toBeInTheDocument();
    });
});
