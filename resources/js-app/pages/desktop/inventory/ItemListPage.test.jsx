import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent, within } from '@testing-library/react';
import ItemListPage from './ItemListPage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: {
        private: () => ({ listen: () => ({ listen: () => {} }), stopListening: () => {} }),
        channel: () => ({ listen: () => {}, stopListening: () => {} }),
        leave: () => {},
    },
}));

const WAREHOUSES = [{ id: 1, name: 'Main' }, { id: 2, name: 'Yard' }];

/**
 * The Edit or Delete button in the row for `name`.
 *
 * By row rather than by position: the list is sorted by name, so an index says
 * nothing about which item is being acted on — and the fixture below is
 * deliberately not in name order.
 */
const rowAction = (name, action) =>
    within(screen.getByText(name).closest('tr')).getByText(action);

const CATEGORY_OPTIONS = [
    { value: 'raw_material:', label: 'Raw Materials', category: 'raw_material', item_category_id: null },
    { value: 'raw_material:1', label: 'Raw Materials / Chemical Materials', category: 'raw_material', item_category_id: 1 },
    { value: 'raw_material:3', label: 'Raw Materials / Bulk', category: 'raw_material', item_category_id: 3 },
    { value: 'finished_good:', label: 'Finished Goods', category: 'finished_good', item_category_id: null },
];

const ITEMS = [
    {
        id: 1, item_code: 'ITEM-00001', item_name: 'Silica Sand', category: 'raw_material',
        unit_of_measure: 'KG', minimum_stock_level: '5', cost_price: '0.006', is_active: true,
        quantity: 18, warehouses: [{ id: 1, name: 'Main', quantity: 12 }, { id: 2, name: 'Yard', quantity: 6 }],
        item_category_id: 3, item_category_name: 'Bulk', category_path: 'Raw Materials / Bulk',
    },
    {
        id: 2, item_code: 'ITEM-00002', item_name: 'Pentaproof 20 P', category: 'raw_material',
        unit_of_measure: 'KG', minimum_stock_level: '2', cost_price: '1.092', is_active: false,
        quantity: 4, warehouses: [{ id: 1, name: 'Main', quantity: 4 }],
        item_category_id: 1, item_category_name: 'Chemical Materials', category_path: 'Raw Materials / Chemical Materials',
        warehouse_id: 1,
    },
    {
        id: 3, item_code: 'ITEM-00003', item_name: 'SUPERBOND F5 White', category: 'finished_good',
        unit_of_measure: 'BAG', minimum_stock_level: '0', cost_price: '0', is_active: true,
        quantity: 7, warehouses: [{ id: 1, name: 'Main', quantity: 7 }],
        item_category_id: null, item_category_name: null, category_path: 'Finished Goods',
    },
];

function renderPage() {
    return render(<ToastProvider><ItemListPage /></ToastProvider>);
}

describe('desktop ItemListPage', () => {
    beforeEach(() => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: ITEMS,
            meta: { category_options: CATEGORY_OPTIONS, warehouses: WAREHOUSES },
        });
    });

    it('lists items from the API', async () => {
        renderPage();
        expect(await screen.findByText('Silica Sand')).toBeInTheDocument();
        expect(screen.getByText('ITEM-00002')).toBeInTheDocument();
    });

    it('renders the category enum as a human label rather than the raw value', async () => {
        renderPage();
        expect(await screen.findAllByText('Raw Material')).not.toHaveLength(0);
        expect(screen.queryByText('raw_material')).not.toBeInTheDocument();
    });

    // The Blade table badged status and category rather than printing Yes/No.
    it('badges status and category per row', async () => {
        renderPage();
        await screen.findByText('Silica Sand');
        expect(screen.getByText('Active')).toHaveClass('badge-green');
        expect(screen.getByText('Inactive')).toHaveClass('badge-gray');
        expect(screen.getAllByText('Raw Material')[0]).toHaveClass('badge-blue');
    });

    it('opens the create modal from the Add Item button', async () => {
        renderPage();
        await screen.findByText('Silica Sand');
        fireEvent.click(screen.getByText('+ Add Item'));
        expect(await screen.findByText('New Item', { selector: 'h2, h3, div' })).toBeTruthy();
        expect(screen.getByLabelText('Item Name')).toBeInTheDocument();
    });

    it('asks for confirmation before deleting', async () => {
        renderPage();
        await screen.findByText('Silica Sand');
        fireEvent.click(rowAction('Silica Sand', 'Delete'));
        expect(await screen.findByText(/"Silica Sand" will be permanently removed/)).toBeInTheDocument();
    });

    it('keeps the row and explains when the API deactivates instead of deleting', async () => {
        vi.spyOn(client, 'apiDelete').mockResolvedValue({
            deactivated: true,
            message: 'Item has stock movements, so it was deactivated rather than deleted.',
        });
        renderPage();
        await screen.findByText('Silica Sand');
        fireEvent.click(rowAction('Silica Sand', 'Delete'));
        fireEvent.click(await screen.findByText('Confirm'));
        await waitFor(() => {
            expect(screen.getByText(/deactivated rather than deleted/)).toBeInTheDocument();
        });
    });

    it('narrows the list to one warehouse and rescopes the quantity to it', async () => {
        renderPage();
        await screen.findByText('Silica Sand');
        expect(screen.getByText('2 items')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Filter by warehouse'), { target: { value: '2' } });

        // Only the Yard holds anything, and it holds 6 of the Silica Sand's 18.
        expect(screen.queryByText('Pentaproof 20 P')).not.toBeInTheDocument();
        expect(screen.getByText('6.00')).toBeInTheDocument();
        expect(screen.queryByText('18.00')).not.toBeInTheDocument();
        expect(screen.getByText('1 items in Yard')).toBeInTheDocument();
    });

    it('offers only the warehouses that actually hold stock', async () => {
        renderPage();
        await screen.findByText('Silica Sand');
        expect([...screen.getByLabelText('Filter by warehouse').options].map((o) => o.textContent))
            .toEqual(['All warehouses', 'Main', 'Yard']);
    });

    it('search runs within the chosen warehouse', async () => {
        renderPage();
        await screen.findByText('Silica Sand');
        fireEvent.change(screen.getByLabelText('Filter by warehouse'), { target: { value: '1' } });
        fireEvent.change(screen.getByLabelText('Search items'), { target: { value: 'pentaproof' } });

        expect(screen.getByText('1 of 2 items in Main')).toBeInTheDocument();
        expect(screen.queryByText('Silica Sand')).not.toBeInTheDocument();
    });

    /**
     * The page is named after a type, so it must show that type. It listing
     * every type was the complaint this whole change started from.
     */
    it('shows only raw materials, and Finished Goods only finished goods', async () => {
        renderPage();
        expect(await screen.findByText('Silica Sand')).toBeInTheDocument();
        expect(screen.getByText('Pentaproof 20 P')).toBeInTheDocument();
        expect(screen.queryByText('SUPERBOND F5 White')).not.toBeInTheDocument();
        expect(screen.getByText('2 items')).toBeInTheDocument();
    });

    it('renders the finished-goods slice under its own title', async () => {
        render(<ToastProvider><ItemListPage category="finished_good" title="Finished Goods" /></ToastProvider>);
        expect(await screen.findByText('SUPERBOND F5 White')).toBeInTheDocument();
        expect(screen.queryByText('Silica Sand')).not.toBeInTheDocument();
        expect(screen.getByText('Finished Goods', { selector: 'h1' })).toBeInTheDocument();
    });

    it('narrows to one section and counts what is on screen', async () => {
        renderPage();
        await screen.findByText('Silica Sand');

        fireEvent.change(screen.getByLabelText('Filter by section'), { target: { value: '1' } });

        expect(screen.getByText('Pentaproof 20 P')).toBeInTheDocument();
        expect(screen.queryByText('Silica Sand')).not.toBeInTheDocument();
        expect(screen.getByText('1 items')).toBeInTheDocument();
    });

    it('offers only the sections present on this page', async () => {
        renderPage();
        await screen.findByText('Silica Sand');
        expect([...screen.getByLabelText('Filter by section').options].map((o) => o.textContent))
            .toEqual(['All sections', 'Bulk', 'Chemical Materials']);
    });

    it('search matches either half of the classification', async () => {
        renderPage();
        await screen.findByText('Silica Sand');
        fireEvent.change(screen.getByLabelText('Search items'), { target: { value: 'chemical' } });
        expect(screen.getByText('Pentaproof 20 P')).toBeInTheDocument();
        expect(screen.queryByText('Silica Sand')).not.toBeInTheDocument();
    });

    /** One control, both columns — choosing a section chooses its type too. */
    it('offers the two-level category as a single dropdown', async () => {
        renderPage();
        await screen.findByText('Silica Sand');
        fireEvent.click(screen.getByText('+ Add Item'));

        const select = await screen.findByLabelText('Category');
        expect([...select.options].map((o) => o.textContent)).toEqual([
            'Raw Materials', 'Raw Materials / Chemical Materials', 'Raw Materials / Bulk', 'Finished Goods',
        ]);
        // A new item on this page starts as the type the page shows.
        expect(select.value).toBe('raw_material:');
    });

    /**
     * Stock levels were only ever created by a stock movement, so there was no
     * way to say where an item lives before any of it had arrived — every
     * imported item read as being nowhere.
     */
    it('offers a warehouse on the form, with an opening stock field once one is picked', async () => {
        renderPage();
        await screen.findByText('Silica Sand');
        fireEvent.click(screen.getByText('+ Add Item'));

        const select = await screen.findByLabelText('Warehouse');
        expect([...select.options].map((o) => o.textContent)).toEqual(['Not assigned', 'Main', 'Yard']);
        expect(screen.queryByLabelText('Opening Stock')).not.toBeInTheDocument();

        fireEvent.change(select, { target: { value: '2' } });
        expect(screen.getByLabelText('Opening Stock')).toBeInTheDocument();
    });

    /** An existing item's quantity belongs to the stock ledger, not this form. */
    it('preselects the warehouse when editing and offers no opening stock', async () => {
        renderPage();
        await screen.findByText('Silica Sand');
        // Pentaproof is the one fixture row that sits in a single warehouse,
        // which is what gives the form a warehouse to preselect.
        fireEvent.click(rowAction('Pentaproof 20 P', 'Edit'));

        expect(await screen.findByLabelText('Warehouse')).toHaveValue('1');
        expect(screen.queryByLabelText('Opening Stock')).not.toBeInTheDocument();
    });

});

/**
 * The sort control. Client-side over every loaded row, like the search beside
 * it, so changing the order costs no round trip.
 */
describe('desktop ItemListPage sorting', () => {
    const SORTABLE = [
        {
            id: 11, item_code: 'ITEM-00100', item_name: 'Zinc Oxide', category: 'raw_material',
            unit_of_measure: 'KG', minimum_stock_level: '0', cost_price: '1', is_active: true,
            quantity: 1, warehouses: [{ id: 1, name: 'Main', quantity: 1 }],
            item_category_id: null, item_category_name: null, category_path: 'Raw Materials',
            last_purchased_at: '2026-09-01',
        },
        {
            id: 12, item_code: 'ITEM-00300', item_name: 'Alpha Cement', category: 'raw_material',
            unit_of_measure: 'KG', minimum_stock_level: '0', cost_price: '1', is_active: true,
            quantity: 1, warehouses: [{ id: 1, name: 'Main', quantity: 1 }],
            item_category_id: null, item_category_name: null, category_path: 'Raw Materials',
            last_purchased_at: '2026-09-20',
        },
        {
            id: 13, item_code: 'ITEM-00200', item_name: 'Mid Sand', category: 'raw_material',
            unit_of_measure: 'KG', minimum_stock_level: '0', cost_price: '1', is_active: true,
            quantity: 1, warehouses: [{ id: 1, name: 'Main', quantity: 1 }],
            item_category_id: null, item_category_name: null, category_path: 'Raw Materials',
            last_purchased_at: null,
        },
    ];

    /** The item names as the table currently has them, top to bottom. */
    const namesOnScreen = () => screen.getAllByRole('row')
        .slice(1)
        .map((row) => row.querySelectorAll('td')[1]?.textContent);

    beforeEach(() => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: SORTABLE,
            meta: { category_options: CATEGORY_OPTIONS, warehouses: WAREHOUSES },
        });
    });

    it('orders by name to begin with', async () => {
        renderPage();
        await screen.findByText('Alpha Cement');

        expect(namesOnScreen()).toEqual(['Alpha Cement', 'Mid Sand', 'Zinc Oxide']);
    });

    it('orders by item code', async () => {
        renderPage();
        await screen.findByText('Alpha Cement');

        fireEvent.change(screen.getByLabelText('Sort items by'), { target: { value: 'code' } });

        // ITEM-00100, ITEM-00200, ITEM-00300 — nothing to do with the names.
        expect(namesOnScreen()).toEqual(['Zinc Oxide', 'Mid Sand', 'Alpha Cement']);
    });

    it('orders by most recently purchased, leaving the never-purchased last', async () => {
        renderPage();
        await screen.findByText('Alpha Cement');

        fireEvent.change(screen.getByLabelText('Sort items by'), { target: { value: 'recent' } });

        // A missing date is not the oldest date, so Mid Sand sorts to the end
        // rather than to the front.
        expect(namesOnScreen()).toEqual(['Alpha Cement', 'Zinc Oxide', 'Mid Sand']);
    });

    it('sorts what the search left, not the whole list', async () => {
        renderPage();
        await screen.findByText('Alpha Cement');

        fireEvent.change(screen.getByLabelText('Sort items by'), { target: { value: 'recent' } });
        fireEvent.change(screen.getByLabelText('Search items'), { target: { value: 'ITEM-002' } });

        expect(namesOnScreen()).toEqual(['Mid Sand']);
    });
});
