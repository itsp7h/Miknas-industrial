import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import ProductionOrderListPage from './ProductionOrderListPage';
import BomListPage from './BomListPage';
import FlowListPage from './FlowListPage';
import MaterialIssueListPage from './MaterialIssueListPage';
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

    // Blade's material issues page was a six-column table (order, item,
    // warehouse, quantity, date, notes) with the create form inline underneath —
    // not a generic table with an Issue # column and a modal.
    it('material issues list Blade\u2019s six columns with the order linked', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{
                id: 1, issue_number: 'MI-00001', production_order_id: 4, production_order_number: 'PO-00004',
                item_name: 'Steel Bar', warehouse_name: 'Main', quantity: '20.00', issue_date: '2026-08-03', notes: null,
            }],
        });
        wrap(<MaterialIssueListPage />);

        await screen.findByText('Steel Bar');
        expect(screen.getAllByRole('columnheader').map((th) => th.textContent))
            .toEqual(['Production Order', 'Item', 'Warehouse', 'Quantity', 'Issue Date', 'Notes']);
        expect(screen.getByText('PO-00004')).toHaveAttribute('href', '/app/production/orders/4');
        expect(screen.getByText('20.00')).toBeInTheDocument();
        expect(screen.getByText('03 Aug 2026')).toBeInTheDocument();
        // Blade printed a dash for an empty note rather than leaving the cell bare.
        expect(screen.getByText('-')).toBeInTheDocument();
    });

    it('material issues keep the create form on the page, not behind a modal', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });
        wrap(<MaterialIssueListPage />);

        expect(await screen.findByText('Issue New Material')).toBeInTheDocument();
        expect(screen.getByLabelText(/Production Order/)).toBeInTheDocument();
        expect(screen.getByText('Issue Material')).toHaveClass('btn-primary');
        expect(screen.getByText('No material issues found.')).toBeInTheDocument();
    });

    it('material issues offer only material actually on hand in the chosen warehouse', async () => {
        vi.spyOn(client, 'apiGet').mockImplementation((url) => (
            url.endsWith('/form-options')
                ? Promise.resolve({
                    production_orders: [{ id: 4, order_number: 'PO-00004', product_name: 'Frame' }],
                    warehouses: [{ id: 1, name: 'Main' }, { id: 2, name: 'Yard' }],
                    stock: [
                        { item_id: 7, item_name: 'Steel Bar', warehouse_id: 1, quantity: '50.00' },
                        { item_id: 8, item_name: 'Bolt', warehouse_id: 2, quantity: '10.00' },
                    ],
                })
                : Promise.resolve({ data: [] })
        ));
        wrap(<MaterialIssueListPage />);

        // Nothing to choose until a warehouse is picked — Blade listed every item
        // in the system and let the request fail.
        expect(await screen.findByText('-- Choose a warehouse first --')).toBeInTheDocument();
        fireEvent.change(screen.getByLabelText(/Warehouse/), { target: { value: '1' } });
        expect(screen.getByText('Steel Bar (50.00 on hand)')).toBeInTheDocument();
        expect(screen.queryByText(/Bolt/)).not.toBeInTheDocument();
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
