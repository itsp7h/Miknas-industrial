import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import ProductionOrderListPage from './ProductionOrderListPage';
import BomListPage from './BomListPage';
import FlowListPage from './FlowListPage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => ({ listen: () => {} }), stopListening: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const wrap = (ui) => render(<MemoryRouter><ToastProvider>{ui}</ToastProvider></MemoryRouter>);

const ORDERS = [
    { id: 1, order_number: 'PO-00001', product_name: 'Frame', quantity_to_produce: '10.00', quantity_produced: '4.00', outstanding: 6, production_date: '2026-08-01', status: 'in_progress' },
    { id: 2, order_number: 'PO-00002', product_name: 'Panel', quantity_to_produce: '5.00', quantity_produced: '0.00', outstanding: 5, production_date: '2026-08-02', status: 'planned' },
];

describe('desktop production pages', () => {
    beforeEach(() => vi.restoreAllMocks());

    // Blade gave target and produced their own right-aligned columns rather than
    // merging them into one "4 / 10" progress cell.
    it('lists qty to produce and produced as separate columns', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: ORDERS });
        wrap(<ProductionOrderListPage />);
        await screen.findByText('PO-00001');
        const headers = screen.getAllByRole('columnheader').map((th) => th.textContent);
        expect(headers).toEqual(['Order #', 'Product', 'Qty to Produce', 'Produced', 'Date', 'Status', 'Actions']);
        expect(screen.getByText('10.00')).toBeInTheDocument();
        expect(screen.getByText('4.00')).toBeInTheDocument();
        expect(screen.queryByText('4 / 10')).not.toBeInTheDocument();
    });

    it('badges the status and formats the date the way Blade did', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [ORDERS[0]] });
        wrap(<ProductionOrderListPage />);
        expect(await screen.findByText('In Progress')).toHaveClass('badge-blue');
        expect(screen.getByText('01 Aug 2026')).toBeInTheDocument();
    });

    it('links each order to its detail page', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [ORDERS[0]] });
        wrap(<ProductionOrderListPage />);
        expect(await screen.findByText('View')).toHaveAttribute('href', '/app/production/orders/1');
    });

    // The actions available depend on where the order is in its lifecycle.
    it('offers Start only on a planned order and Complete only on one in progress', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: ORDERS });
        wrap(<ProductionOrderListPage />);
        await screen.findByText('PO-00001');
        expect(screen.getAllByText('Start')).toHaveLength(1);
        expect(screen.getAllByText('Complete')).toHaveLength(1);
        expect(screen.getAllByText('Edit')).toHaveLength(1);
        // Blade guarded Start on 'pending', which the status enum never contains,
        // so the button could never appear.
        expect(screen.getAllByText('View')).toHaveLength(2);
    });

    it('warns that completing cannot be reopened before doing it', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: ORDERS });
        wrap(<ProductionOrderListPage />);
        await screen.findByText('PO-00001');
        fireEvent.click(screen.getByText('Complete'));
        expect(await screen.findByText(/cannot be reopened/)).toBeInTheDocument();
    });

    it('surfaces a refused transition as an error toast', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: ORDERS });
        vi.spyOn(client, 'apiPatch').mockRejectedValue({ message: 'Only a planned order can be started.' });
        wrap(<ProductionOrderListPage />);
        await screen.findByText('PO-00001');
        fireEvent.click(screen.getByText('Start'));
        fireEvent.click(await screen.findByText('Confirm'));
        await waitFor(() => {
            expect(screen.getByText('Only a planned order can be started.')).toBeInTheDocument();
        });
    });

    // Blade grouped the lines into one card per product, with the product's name
    // and code in a blue header — not a flat table repeating the product on every
    // row, which is what the first React port produced.
    it('bill of materials groups lines under one card per product', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [
                { id: 1, product_id: 1, product_name: 'Frame', product_code: 'FG-1', raw_material_name: 'Steel Bar', quantity_required: '2.50', unit_of_measure: 'KG' },
                { id: 2, product_id: 1, product_name: 'Frame', product_code: 'FG-1', raw_material_name: 'Bolt', quantity_required: '8.00', unit_of_measure: 'PCS' },
                { id: 3, product_id: 2, product_name: 'Panel', product_code: 'FG-2', raw_material_name: 'Steel Sheet', quantity_required: '1.00', unit_of_measure: 'SQM' },
            ],
        });
        const { container } = wrap(<BomListPage />);

        await screen.findByText('Frame');
        expect(container.querySelectorAll('table')).toHaveLength(2);
        // The product is named once per card, not once per line.
        expect(screen.getAllByText('Frame')).toHaveLength(1);
        expect(screen.getByText('FG-1')).toBeInTheDocument();
        expect(screen.getByText('2.50')).toBeInTheDocument();
        expect(screen.getByText('Bolt')).toBeInTheDocument();
    });

    it('bill of materials offers Edit and Delete on every line', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 1, product_id: 1, product_name: 'Frame', product_code: 'FG-1', raw_material_name: 'Steel Bar', quantity_required: '2.50', unit_of_measure: 'KG' }],
        });
        wrap(<BomListPage />);

        expect(await screen.findByText('Edit')).toHaveClass('btn-secondary');
        expect(screen.getByText('Delete')).toHaveClass('btn-danger');
    });

    it('bill of materials filters client-side with a live count', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [
                { id: 1, product_id: 1, product_name: 'Frame', product_code: 'FG-1', raw_material_name: 'Steel Bar', quantity_required: '2.50', unit_of_measure: 'KG' },
                { id: 3, product_id: 2, product_name: 'Panel', product_code: 'FG-2', raw_material_name: 'Steel Sheet', quantity_required: '1.00', unit_of_measure: 'SQM' },
            ],
        });
        wrap(<BomListPage />);

        await screen.findByText('Frame');
        expect(screen.getByText('2 entries')).toBeInTheDocument();
        fireEvent.change(screen.getByLabelText('Search bill of materials'), { target: { value: 'panel' } });
        expect(screen.getByText('1 of 2 entries')).toBeInTheDocument();
        expect(screen.queryByText('Frame')).not.toBeInTheDocument();
    });

    it('bill of materials says so when there is nothing defined', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });
        wrap(<BomListPage />);

        expect(await screen.findByText(/No BOM entries found/)).toBeInTheDocument();
        expect(screen.getByText('Add the first one')).toBeInTheDocument();
    });

    it('material issues and output share a page but differ in title and columns', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 1, issue_number: 'MI-00001', issue_date: '2026-08-03', production_order_number: 'PO-00001', item_name: 'Steel Bar', warehouse_name: 'Main', quantity: '20.00', notes: null }],
        });
        const { unmount } = wrap(<FlowListPage kind="material-issue" />);
        expect(await screen.findByText('Material Issues')).toBeInTheDocument();
        expect(screen.getByText('MI-00001')).toBeInTheDocument();
        unmount();

        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 2, output_date: '2026-08-04', production_order_number: 'PO-00001', item_name: 'Frame', warehouse_name: 'Main', quantity: '4.00', notes: null }],
        });
        wrap(<FlowListPage kind="production-output" />);
        expect(await screen.findByText('Production Output')).toBeInTheDocument();
        expect(screen.getByText('Record Output')).toBeInTheDocument();
    });
});
