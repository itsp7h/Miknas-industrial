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
    purchase_request: { id: 4, request_number: 'MPR-0004', company_name: 'Plant Expansion' },
    items: [
        { id: 11, item_name: 'Steel Plate', unit_of_measure: 'KG', quantity: '10.00', rate: '30.00', total_amount: '300.00' },
        { id: 12, item_name: 'Bolt', unit_of_measure: 'PCS', quantity: '9.00', rate: '5.00', total_amount: '45.00' },
    ],
    goods_receipt_notes: [
        { id: 3, grn_number: 'GRN-00003', warehouse_name: 'Main', received_date: '2026-08-14', status: 'confirmed' },
    ],
};

describe('PurchaseOrderDetail', () => {
    /**
     * The page shows the LPO itself — the sheet Print opens and the PDF is
     * drawn from — not a React copy of it, which had drifted.
     */
    it('shows the printed LPO in a frame, not a lookalike', () => {
        render(<MemoryRouter><PurchaseOrderDetail order={ORDER} /></MemoryRouter>);

        const frame = screen.getByTitle('LPO PO-00007');
        expect(frame.tagName).toBe('IFRAME');
        expect(frame).toHaveAttribute('src', '/purchase/orders/7/print?embed=1');
        expect(screen.queryByText('Local Purchase Order')).not.toBeInTheDocument();
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
});
