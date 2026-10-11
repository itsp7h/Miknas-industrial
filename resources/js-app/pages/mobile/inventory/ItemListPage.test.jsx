import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import ItemListPage from './ItemListPage';
import { ToastProvider } from '../../../components/ui/Toast';
import { AccessProvider } from '../../../layouts/AccessContext';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: {
        private: () => ({ listen: () => ({ listen: () => {} }), stopListening: () => {} }),
        channel: () => ({ listen: () => {}, stopListening: () => {} }),
        leave: () => {},
    },
}));

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
    },
    {
        id: 3, item_code: 'ITEM-00003', item_name: 'SUPERBOND F5 White', category: 'finished_good',
        unit_of_measure: 'BAG', minimum_stock_level: '0', cost_price: '0', is_active: true,
        quantity: 7, warehouses: [{ id: 1, name: 'Main', quantity: 7 }],
        item_category_id: null, item_category_name: null, category_path: 'Finished Goods',
    },
];

function renderPage() {
    return render(
        <ToastProvider>
            <AccessProvider isAdmin>
                <MemoryRouter><ItemListPage /></MemoryRouter>
            </AccessProvider>
        </ToastProvider>
    );
}

// Sort, warehouse and section live in the filter sheet.
const openFilters = () => fireEvent.click(screen.getByRole('button', { name: 'Sort and filter' }));

describe('mobile ItemListPage', () => {
    beforeEach(() => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: ITEMS, meta: { category_options: CATEGORY_OPTIONS } });
    });

    it('renders items as cards rather than a table', async () => {
        const { container } = renderPage();
        expect(await screen.findByText('Silica Sand')).toBeInTheDocument();
        expect(container.querySelector('table')).toBeNull();
    });

    // CLAUDE.md gotcha #6: search filters client-side over the whole list,
    // with a live count and a no-results message. No refetch, no URL params.
    it('filters client-side and reports a live count', async () => {
        renderPage();
        await screen.findByText('Silica Sand');
        expect(screen.getByText(/^2 items · Name/)).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Search items'), { target: { value: 'pentaproof' } });

        expect(screen.getByText(/^1 of 2 items/)).toBeInTheDocument();
        expect(screen.queryByText('Silica Sand')).not.toBeInTheDocument();
        expect(screen.getByText('Pentaproof 20 P')).toBeInTheDocument();
    });

    it('searches on item code too, not just name', async () => {
        renderPage();
        await screen.findByText('Silica Sand');
        fireEvent.change(screen.getByLabelText('Search items'), { target: { value: 'ITEM-00002' } });
        expect(screen.getByText('Pentaproof 20 P')).toBeInTheDocument();
        expect(screen.queryByText('Silica Sand')).not.toBeInTheDocument();
    });

    it('shows a no-results message instead of an empty list', async () => {
        renderPage();
        await screen.findByText('Silica Sand');
        fireEvent.change(screen.getByLabelText('Search items'), { target: { value: 'zzzz' } });
        expect(screen.getByText('No items match that search')).toBeInTheDocument();
    });

    it('does not refetch while searching', async () => {
        renderPage();
        await screen.findByText('Silica Sand');
        const callsBefore = client.apiGet.mock.calls.length;
        fireEvent.change(screen.getByLabelText('Search items'), { target: { value: 'steel' } });
        expect(client.apiGet.mock.calls.length).toBe(callsBefore);
    });

    it('puts import, template and PDF behind a tools sheet to keep the header clean', async () => {
        renderPage();
        await screen.findByText('Silica Sand');
        expect(screen.queryByText('Download import template')).not.toBeInTheDocument();
        fireEvent.click(screen.getByText(/Import, template/));
        expect(screen.getByText('Download import template').closest('a')).toHaveAttribute('href', '/api/v1/inventory/items/template');
        expect(screen.getByText('Export as PDF').closest('a')).toHaveAttribute('href', '/api/v1/inventory/items/export-pdf');
        expect(screen.getByRole('button', { name: 'Import from Excel' })).toBeEnabled();
    });

    it('opens an item to edit or delete it', async () => {
        renderPage();
        fireEvent.click(await screen.findByText('Silica Sand'));
        expect(screen.getByRole('button', { name: 'Edit item' })).toBeEnabled();
        expect(screen.getByRole('button', { name: 'Delete item' })).toBeEnabled();
    });

    it('narrows the cards to one warehouse and rescopes the quantity to it', async () => {
        renderPage();
        await screen.findByText('Silica Sand');

        openFilters();
        fireEvent.change(screen.getByLabelText('Filter by warehouse'), { target: { value: '2' } });

        expect(screen.queryByText('Pentaproof 20 P')).not.toBeInTheDocument();
        expect(screen.getByText('6.00')).toBeInTheDocument();
        // The header names the warehouse in view.
        expect(screen.getByTestId('mobile-hero')).toHaveTextContent('Yard');
    });

    it('shows only raw materials and filters by section', async () => {
        renderPage();
        expect(await screen.findByText('Silica Sand')).toBeInTheDocument();
        expect(screen.queryByText('SUPERBOND F5 White')).not.toBeInTheDocument();

        openFilters();
        fireEvent.change(screen.getByLabelText('Filter by section'), { target: { value: '1' } });

        expect(screen.getByText('Pentaproof 20 P')).toBeInTheDocument();
        expect(screen.queryByText('Silica Sand')).not.toBeInTheDocument();
    });

});

/** The sort control is a pair, like every page (CLAUDE.md gotcha #12). */
describe('mobile ItemListPage sorting', () => {
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

    beforeEach(() => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: SORTABLE, meta: { category_options: CATEGORY_OPTIONS },
        });
    });

    /** The card titles in the order the list currently has them. */
    const namesOnScreen = () => SORTABLE
        .map((item) => ({ name: item.item_name, node: screen.getByText(item.item_name) }))
        .sort((a, b) => (a.node.compareDocumentPosition(b.node) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1))
        .map((entry) => entry.name);

    it('offers the same three orders as the desktop page', async () => {
        renderPage();
        await screen.findByText('Alpha Cement');

        expect(namesOnScreen()).toEqual(['Alpha Cement', 'Mid Sand', 'Zinc Oxide']);

        openFilters();
        fireEvent.change(screen.getByLabelText('Sort items by'), { target: { value: 'code' } });
        expect(namesOnScreen()).toEqual(['Zinc Oxide', 'Mid Sand', 'Alpha Cement']);

        fireEvent.change(screen.getByLabelText('Sort items by'), { target: { value: 'recent' } });
        expect(namesOnScreen()).toEqual(['Alpha Cement', 'Zinc Oxide', 'Mid Sand']);
    });
});
