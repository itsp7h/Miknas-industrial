import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import PurchaseOrderDetail from './PurchaseOrderDetail';

const ORDER = {
    id: 7,
    po_number: 'PO-00007',
    status: 'sent',
    po_date: '2026-08-01',
    expected_delivery_date: '2026-08-12',
    total_amount: '345.00',
    notes: 'Deliver to gate 3.',
    created_by_name: 'Yousif',
    company_name: 'Miknas Industrial',
    supplier: {
        id: 2, name: 'Gulf Metals', contact_person: 'Ali',
        address: 'Sitra', phone: '17000000', email: 'ali@gulf.example',
    },
    purchase_request: { id: 4, request_number: 'MPR-0004', project_name: 'Plant Expansion' },
    items: [
        { id: 11, item_name: 'Steel Plate', unit_of_measure: 'KG', quantity: '10.00', rate: '30.00', total_amount: '300.00' },
        { id: 12, item_name: 'Bolt', unit_of_measure: 'PCS', quantity: '9.00', rate: '5.00', total_amount: '45.00' },
    ],
    goods_receipt_notes: [
        { id: 3, grn_number: 'GRN-00003', warehouse_name: 'Main', received_date: '2026-08-14', status: 'confirmed' },
    ],
};

describe('PurchaseOrderDetail', () => {
    it('heads the sheet with the project company and the LPO title', () => {
        render(<MemoryRouter><PurchaseOrderDetail order={ORDER} /></MemoryRouter>);
        expect(screen.getByText('Miknas Industrial')).toBeInTheDocument();
        expect(screen.getByText('Local Purchase Order')).toBeInTheDocument();
        expect(screen.getByText('PO-00007')).toBeInTheDocument();
        expect(screen.getByText('Sent')).toBeInTheDocument();
    });

    it('shows the full supplier block, not just the name', () => {
        render(<MemoryRouter><PurchaseOrderDetail order={ORDER} /></MemoryRouter>);
        ['Gulf Metals', 'Ali', 'Sitra', '17000000', 'ali@gulf.example'].forEach((text) => {
            expect(screen.getByText(text)).toBeInTheDocument();
        });
    });

    it('shows the reference MPR and the notes', () => {
        render(<MemoryRouter><PurchaseOrderDetail order={ORDER} /></MemoryRouter>);
        expect(screen.getByText('MPR-0004')).toBeInTheDocument();
        expect(screen.getByText('Deliver to gate 3.')).toBeInTheDocument();
    });

    it('renders lines in a table on desktop and totals them', () => {
        render(<MemoryRouter><PurchaseOrderDetail order={ORDER} /></MemoryRouter>);
        expect(screen.getByRole('table')).toBeInTheDocument();
        expect(screen.getByText('Steel Plate')).toBeInTheDocument();
        expect(screen.getByText('BD 345.00')).toBeInTheDocument();
    });

    // A five-column table cannot be read on a phone, so mobile stacks the lines.
    it('stacks lines instead of tabulating them when compact', () => {
        render(<MemoryRouter><PurchaseOrderDetail order={ORDER} compact /></MemoryRouter>);
        expect(screen.queryByRole('table')).not.toBeInTheDocument();
        expect(screen.getByText('1. Steel Plate')).toBeInTheDocument();
        expect(screen.getByText('Total Amount')).toBeInTheDocument();
    });

    it('lists linked GRNs with a link to each', () => {
        render(<MemoryRouter><PurchaseOrderDetail order={ORDER} /></MemoryRouter>);
        expect(screen.getByText('GRN-00003')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'View' })).toHaveAttribute('href', '/app/purchase/grns/3');
    });

    it('omits the GRN panel entirely when nothing has been received', () => {
        render(<MemoryRouter><PurchaseOrderDetail order={{ ...ORDER, goods_receipt_notes: [] }} /></MemoryRouter>);
        expect(screen.queryByText('Goods Receipt Notes')).not.toBeInTheDocument();
    });

    it('renders nothing rather than crashing before the order loads', () => {
        const { container } = render(<MemoryRouter><PurchaseOrderDetail order={null} /></MemoryRouter>);
        expect(container).toBeEmptyDOMElement();
    });

    it('falls back to an em dash for a missing delivery date', () => {
        render(<MemoryRouter><PurchaseOrderDetail order={{ ...ORDER, expected_delivery_date: null }} /></MemoryRouter>);
        expect(screen.getByText('—')).toBeInTheDocument();
    });
});
