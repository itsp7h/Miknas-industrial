import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import GrnTable from './GrnTable';
import GrnDetail from './GrnDetail';
import { badgeClassFor, confirmBlockedReason, formatDate, needsLabel, qty } from './grnStyles';

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

describe('missing documents', () => {
    it('names what a receipt still needs, and nothing once it has it all', () => {
        expect(needsLabel({ missing_documents: ['Tax Invoice'] })).toBe('Needs Tax Invoice');
        expect(needsLabel({ missing_documents: ['LPO', 'Tax Invoice'] })).toBe('Needs LPO & Tax Invoice');
        expect(needsLabel({ missing_documents: ['LPO', 'GRN', 'Tax Invoice'] })).toBe('Needs LPO, GRN & Tax Invoice');
        expect(needsLabel({ missing_documents: [] })).toBeNull();
        expect(needsLabel({})).toBeNull();
    });

    it('says why Confirm is blocked', () => {
        expect(confirmBlockedReason({ missing_documents: ['GRN'] })).toBe('Upload the GRN to confirm this GRN.');
        expect(confirmBlockedReason({ missing_documents: [] })).toBeNull();
    });
});

describe('GrnTable', () => {
    const renderTable = (rows = GRNS, handlers = {}) =>
        renderIn(<GrnTable grns={rows} onConfirm={handlers.onConfirm ?? (() => {})} onDelete={handlers.onDelete ?? (() => {})} />);

    /** Saved with paperwork missing: flagged, and not confirmable until it is in. */
    it('flags a receipt that needs a document and disables its Confirm', () => {
        const onConfirm = vi.fn();
        renderTable([{ ...GRNS[0], status: 'draft', missing_documents: ['Tax Invoice'] }], { onConfirm });

        expect(screen.getByText('Needs Tax Invoice')).toHaveClass('badge-red');
        const confirm = screen.getByRole('button', { name: 'Confirm' });
        expect(confirm).toBeDisabled();
        expect(confirm).toHaveAttribute('title', 'Upload the Tax Invoice to confirm this GRN.');
        fireEvent.click(confirm);
        expect(onConfirm).not.toHaveBeenCalled();
    });

    it('lets a receipt with all three documents be confirmed', () => {
        const onConfirm = vi.fn();
        renderTable([{ ...GRNS[0], status: 'draft', missing_documents: [] }], { onConfirm });

        expect(screen.queryByText(/^Needs /)).not.toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));
        expect(onConfirm).toHaveBeenCalled();
    });

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

    it('lists the LPO, GRN and tax invoice, each opening in a new tab', () => {
        renderIn(<GrnDetail grn={{
            ...GRN,
            documents: [
                { kind: 'lpo', label: 'LPO', name: 'lpo.pdf', size: 250 * 1024, url: '/purchase/grns/1/documents/lpo' },
                { kind: 'grn', label: 'GRN', name: 'delivery.jpg', size: 2.5 * 1024 * 1024, url: '/purchase/grns/1/documents/grn' },
                { kind: 'tax_invoice', label: 'Tax Invoice', name: null, size: null, url: null },
            ],
        }} />);

        expect(screen.getByText('Documents')).toBeInTheDocument();
        const lpo = screen.getByRole('link', { name: 'lpo.pdf' });
        expect(lpo).toHaveAttribute('href', '/purchase/grns/1/documents/lpo');
        expect(lpo).toHaveAttribute('target', '_blank');
        expect(screen.getByText(/250 KB/)).toBeInTheDocument();
        expect(screen.getByText(/2\.5 MB/)).toBeInTheDocument();
        expect(screen.getByText('Not uploaded')).toBeInTheDocument();
    });

    it('lists each Other file, or None', () => {
        const documents = [{ kind: 'lpo', label: 'LPO', name: null, size: null, url: null }];
        const { unmount } = renderIn(<GrnDetail grn={{
            ...GRN, documents,
            other_documents: [
                { id: 21, name: 'packing-list.pdf', size: 40 * 1024, url: '/purchase/grns/1/documents/other/21' },
                { id: 22, name: 'photo.jpg', size: 900 * 1024, url: '/purchase/grns/1/documents/other/22' },
            ],
        }} />);

        expect(screen.getByText('Other')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'packing-list.pdf' })).toHaveAttribute('href', '/purchase/grns/1/documents/other/21');
        expect(screen.getByRole('link', { name: 'photo.jpg' })).toHaveAttribute('target', '_blank');
        unmount();

        renderIn(<GrnDetail grn={{ ...GRN, documents, other_documents: [] }} />);
        expect(screen.getByText('None')).toBeInTheDocument();
    });

    it('offers Upload for each missing document and sends the chosen file', () => {
        const onUpload = vi.fn();
        renderIn(<GrnDetail canUpload onUpload={onUpload} grn={{
            ...GRN, missing_documents: ['Tax Invoice'], other_documents: [],
            documents: [
                { kind: 'lpo', label: 'LPO', name: 'lpo.pdf', size: 1024, url: '/purchase/grns/1/documents/lpo' },
                { kind: 'grn', label: 'GRN', name: 'grn.pdf', size: 1024, url: '/purchase/grns/1/documents/grn' },
                { kind: 'tax_invoice', label: 'Tax Invoice', name: null, size: null, url: null },
            ],
        }} />);

        expect(screen.getByText('Needs Tax Invoice')).toBeInTheDocument();
        expect(screen.queryByLabelText('Upload LPO')).not.toBeInTheDocument();
        const file = new File(['%PDF'], 'invoice.pdf', { type: 'application/pdf' });
        fireEvent.change(screen.getByLabelText('Upload Tax Invoice'), { target: { files: [file] } });
        expect(onUpload).toHaveBeenCalledWith('tax_invoice_document', [file]);

        fireEvent.change(screen.getByLabelText('Add other files'), { target: { files: [file] } });
        expect(onUpload).toHaveBeenLastCalledWith('other_documents', [file]);
    });

    it('shows Upload disabled, with the reason, to someone who may not add documents', () => {
        renderIn(<GrnDetail canUpload={false} onUpload={() => {}} grn={{
            ...GRN, missing_documents: ['LPO'], other_documents: [],
            documents: [{ kind: 'lpo', label: 'LPO', name: null, size: null, url: null }],
        }} />);

        const input = screen.getByLabelText('Upload LPO');
        expect(input).toBeDisabled();
        expect(input.closest('label')).toHaveAttribute('title', 'You do not have permission to add documents to goods receipts');
    });

    it('leaves the documents card out when the payload carries none', () => {
        renderIn(<GrnDetail grn={GRN} />);
        expect(screen.queryByText('Documents')).not.toBeInTheDocument();
    });

    it('renders nothing rather than crashing before the GRN loads', () => {
        const { container } = renderIn(<GrnDetail grn={null} />);
        expect(container).toBeEmptyDOMElement();
    });
});
