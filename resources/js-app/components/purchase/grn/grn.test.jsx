import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import GrnTable from './GrnTable';
import GrnDetail from './GrnDetail';
import { badgeClassFor, formatDate, qty } from './grnStyles';

const GRNS = [
    {
        id: 1, grn_number: 'GRN-00001', purchase_order_id: 3, po_number: 'PO-00003',
        supplier_name: 'Al Rawabi Trading', warehouse_name: 'Main Store',
        received_date: '2026-08-25', status: 'confirmed',
    },
    {
        id: 2, grn_number: 'GRN-00002', purchase_order_id: 4, po_number: 'PO-00004',
        supplier_name: 'Gulf Metals', warehouse_name: 'Main Store',
        received_date: '2026-08-28', status: 'draft',
    },
];

const renderIn = (ui) => render(<MemoryRouter>{ui}</MemoryRouter>);

describe('grnStyles', () => {
    /**
     * The Blade pages hardcoded badge-green for every status, so a draft GRN
     * looked identical to a confirmed one. Draft is amber here.
     */
    it('distinguishes draft from confirmed, unlike the Blade pages', () => {
        expect(badgeClassFor('draft')).toBe('badge-yellow');
        expect(badgeClassFor('confirmed')).toBe('badge-green');
    });

    it('falls back to the draft badge for an unknown status', () => {
        expect(badgeClassFor('nonsense')).toBe('badge-yellow');
    });

    it('formats dates as d M Y and quantities to two decimals', () => {
        expect(formatDate('2026-09-01')).toBe('01 Sep 2026');
        expect(formatDate(null)).toBe('—');
        expect(qty('4')).toBe('4.00');
    });
});

describe('GrnTable', () => {
    const renderTable = (rows = GRNS, handlers = {}) =>
        renderIn(<GrnTable grns={rows} onConfirm={handlers.onConfirm ?? (() => {})} onDelete={handlers.onDelete ?? (() => {})} />);

    it('renders all seven Blade columns', () => {
        renderTable();
        ['GRN #', 'PO #', 'Supplier', 'Warehouse', 'Date', 'Status', 'Actions']
            .forEach((heading) => expect(screen.getByText(heading)).toBeInTheDocument());
    });

    it('links the GRN and its purchase order at the React routes', () => {
        renderTable();
        expect(screen.getByText('GRN-00001').closest('a')).toHaveAttribute('href', '/app/purchase/grns/1');
        expect(screen.getByText('PO-00003').closest('a')).toHaveAttribute('href', '/app/purchase/orders/3');
    });

    it('badges each status distinctly', () => {
        renderTable();
        expect(screen.getByText('Confirmed')).toHaveClass('badge-green');
        expect(screen.getByText('Draft')).toHaveClass('badge-yellow');
    });

    /**
     * Confirming is what receives the stock, and a confirmed GRN must not be
     * re-confirmed or deleted — so those actions appear on drafts only.
     */
    it('offers Confirm and Delete on a draft only', () => {
        renderTable();
        expect(screen.getAllByText('Confirm')).toHaveLength(1);
        expect(screen.getAllByText('Delete')).toHaveLength(1);
        expect(screen.getAllByText('View')).toHaveLength(2);
    });

    it('calls back with the row on confirm and delete', () => {
        const onConfirm = vi.fn();
        const onDelete = vi.fn();
        renderTable([GRNS[1]], { onConfirm, onDelete });

        fireEvent.click(screen.getByText('Confirm'));
        fireEvent.click(screen.getByText('Delete'));

        expect(onConfirm).toHaveBeenCalledWith(GRNS[1]);
        expect(onDelete).toHaveBeenCalledWith(GRNS[1]);
    });

    it('says so when there are none', () => {
        renderTable([]);
        expect(screen.getByText('No GRNs found.')).toBeInTheDocument();
    });
});

describe('GrnDetail', () => {
    const GRN = {
        ...GRNS[0],
        notes: 'Two crates damaged.',
        received_by_name: 'Yousif',
        items: [
            { id: 11, item_name: 'Steel Plate', quantity_ordered: '10.00', quantity_received: '4.00' },
        ],
    };

    it('shows the details card and links the purchase order', () => {
        renderIn(<GrnDetail grn={GRN} />);
        expect(screen.getByText('GRN Details')).toBeInTheDocument();
        expect(screen.getByText('Al Rawabi Trading')).toBeInTheDocument();
        expect(screen.getByText('PO-00003').closest('a')).toHaveAttribute('href', '/app/purchase/orders/3');
        expect(screen.getByText('25 Aug 2026')).toBeInTheDocument();
    });

    it('shows the notes the Blade create form used to discard', () => {
        renderIn(<GrnDetail grn={GRN} />);
        expect(screen.getByText('Two crates damaged.')).toBeInTheDocument();
    });

    /**
     * The Blade show page printed a non-existent grn_items column here, so PO
     * Qty always read 0.00; it now comes from the linked PO line.
     */
    it('shows the real ordered quantity beside the received one', () => {
        renderIn(<GrnDetail grn={GRN} />);
        expect(screen.getByText('10.00')).toBeInTheDocument();
        expect(screen.getByText('4.00')).toBeInTheDocument();
    });

    it('says so when no items were recorded', () => {
        renderIn(<GrnDetail grn={{ ...GRN, items: [] }} />);
        expect(screen.getByText('No items recorded.')).toBeInTheDocument();
    });

    it('renders nothing rather than crashing before the GRN loads', () => {
        const { container } = renderIn(<GrnDetail grn={null} />);
        expect(container).toBeEmptyDOMElement();
    });
});
