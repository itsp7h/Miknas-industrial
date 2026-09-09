import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import SupplierInvoiceTable from './SupplierInvoiceTable';
import { badgeClassFor, formatDate, money } from './invoiceStyles';

const INVOICES = [
    {
        id: 1, invoice_number: 'INV-001', supplier_name: 'Gulf Metals', po_number: 'PO-00003',
        invoice_date: '2026-08-20', total_amount: '110.00', paid_amount: '110.00',
        outstanding: '0.00', status: 'paid',
    },
    {
        id: 2, invoice_number: 'INV-002', supplier_name: 'Zenith Steel', po_number: null,
        invoice_date: '2026-08-25', total_amount: '500.00', paid_amount: '200.00',
        outstanding: '300.00', status: 'partial',
    },
    {
        id: 3, invoice_number: 'INV-003', supplier_name: 'Al Rawabi', po_number: null,
        invoice_date: '2026-08-28', total_amount: '75.50', paid_amount: '0.00',
        outstanding: '75.50', status: 'unpaid',
    },
];

describe('invoiceStyles', () => {
    it('maps each status to the Blade badge class', () => {
        expect(badgeClassFor('unpaid')).toBe('badge-red');
        expect(badgeClassFor('partial')).toBe('badge-yellow');
        expect(badgeClassFor('paid')).toBe('badge-green');
        expect(badgeClassFor('odd')).toBe('badge-gray');
    });

    it('formats money to two decimals and dates as d M Y', () => {
        expect(money('75.5')).toBe('75.50');
        expect(formatDate('2026-09-01')).toBe('01 Sep 2026');
    });
});

describe('SupplierInvoiceTable', () => {
    const renderTable = (rows = INVOICES, handlers = {}) =>
        render(<MemoryRouter><SupplierInvoiceTable
            invoices={rows}
            onEdit={handlers.onEdit ?? (() => {})}
            onDelete={handlers.onDelete ?? (() => {})}
        /></MemoryRouter>);

    it('renders all nine Blade columns', () => {
        renderTable();
        // Scoped to the header row: "Paid" is also a status badge below it.
        const headers = screen.getAllByRole('columnheader').map((th) => th.textContent);
        expect(headers).toEqual([
            'Invoice #', 'Supplier', 'PO #', 'Date', 'Total', 'Paid', 'Outstanding', 'Status', 'Actions',
        ]);
    });

    // "Paid" is both a column header and a status, so match the badge element.
    const badge = (text) => screen.getAllByText(text).find((el) => el.className.includes('badge'));

    it('badges each status distinctly', () => {
        renderTable();
        expect(badge('Paid')).toHaveClass('badge-green');
        expect(badge('Partial')).toHaveClass('badge-yellow');
        expect(badge('Unpaid')).toHaveClass('badge-red');
    });

    /**
     * The Blade index emphasised a non-zero outstanding figure in red and greyed
     * a settled one — the column readers scan first. Rendered one row at a time
     * because the money figures repeat across rows.
     */
    it('reddens a non-zero outstanding figure', () => {
        renderTable([INVOICES[1]]);
        expect(screen.getByText('300.00')).toHaveClass('text-red-600', 'font-semibold');
    });

    it('greys a settled outstanding figure', () => {
        renderTable([INVOICES[0]]);
        // 110.00 appears as both total and paid; 0.00 is the outstanding cell alone.
        expect(screen.getByText('0.00')).toHaveClass('text-gray-500');
    });

    it('shows a dash where no purchase order is linked', () => {
        renderTable([INVOICES[1]]);
        expect(screen.getByText('-')).toBeInTheDocument();
    });

    /** Pay hands the invoice id to the payments page, which preselects it. */
    it('links Pay at the React payments page with the invoice id', () => {
        renderTable([INVOICES[2]]);
        expect(screen.getByText('Pay').closest('a'))
            .toHaveAttribute('href', '/app/purchase/payments?invoice_id=3');
    });

    it('calls back with the row on edit and delete', () => {
        const onEdit = vi.fn();
        const onDelete = vi.fn();
        renderTable([INVOICES[0]], { onEdit, onDelete });

        fireEvent.click(screen.getByText('Edit'));
        fireEvent.click(screen.getByText('Delete'));

        expect(onEdit).toHaveBeenCalledWith(INVOICES[0]);
        expect(onDelete).toHaveBeenCalledWith(INVOICES[0]);
    });

    it('says so when there are none', () => {
        renderTable([]);
        expect(screen.getByText('No invoices found.')).toBeInTheDocument();
    });
});
