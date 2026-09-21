import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import ItemTable from './ItemTable';
import ItemToolbar from './ItemToolbar';
import ItemImportModal from './ItemImportModal';
import {
    categoryBadgeClass, categoryLabel, isLow, num,
    scopeToWarehouse, sectionOptions, warehouseBreakdown, warehouseLabel, warehouseOptions,
} from './itemStyles';

const ITEMS = [
    {
        id: 1, item_code: 'RM-1001', item_name: 'Steel Plate 10mm', category: 'raw_material',
        unit_of_measure: 'KG', minimum_stock_level: '10.00', quantity: 42.5, cost_price: '3.25', is_active: true,
        warehouses: [{ id: 1, name: 'Main', quantity: 30 }, { id: 2, name: 'Yard', quantity: 12.5 }],
        item_category_id: 3, item_category_name: 'Bulk', category_path: 'Raw Materials / Bulk',
    },
    {
        id: 2, item_code: 'FG-2001', item_name: 'Widget', category: 'finished_good',
        unit_of_measure: 'PCS', minimum_stock_level: '5.00', quantity: 1, cost_price: '40.00', is_active: false,
        warehouses: [{ id: 1, name: 'Main', quantity: 1 }],
        item_category_id: null, item_category_name: null, category_path: 'Finished Goods',
    },
    {
        id: 3, item_code: 'WIP-1', item_name: 'Half Widget', category: 'wip',
        unit_of_measure: 'PCS', minimum_stock_level: '0.00', quantity: 0, cost_price: '20.00', is_active: true,
        warehouses: [],
        item_category_id: 1, item_category_name: 'Chemical Materials', category_path: 'Work In Progress / Chemical Materials',
    },
];

describe('itemStyles', () => {
    it('badges each category with the Blade class', () => {
        expect(categoryBadgeClass('raw_material')).toBe('badge-blue');
        expect(categoryBadgeClass('wip')).toBe('badge-yellow');
        expect(categoryBadgeClass('finished_good')).toBe('badge-green');
        expect(categoryBadgeClass('unknown')).toBe('badge-gray');
    });

    /** The Blade table abbreviated 'wip' to "WIP" even though the form spells it out. */
    it('uses the table label for wip, not the form label', () => {
        expect(categoryLabel('wip')).toBe('WIP');
        expect(categoryLabel('raw_material')).toBe('Raw Material');
    });

    it('formats figures to two decimals', () => {
        expect(num('3.2')).toBe('3.20');
        expect(num(null)).toBe('0.00');
    });

    it('offers every section present, and nothing for an unsectioned list', () => {
        expect(sectionOptions(ITEMS)).toEqual([
            { id: 1, name: 'Chemical Materials' },
            { id: 3, name: 'Bulk' },
        ].sort((a, b) => a.name.localeCompare(b.name)));
        expect(sectionOptions([ITEMS[1]])).toEqual([]);
    });

    it('names the one warehouse, or the biggest and a count of the rest', () => {
        expect(warehouseLabel(ITEMS[1])).toBe('Main');
        expect(warehouseLabel(ITEMS[0])).toBe('Main +1');
        expect(warehouseLabel(ITEMS[2])).toBe('—');
    });

    it('keeps the full per-warehouse split for the cell title', () => {
        expect(warehouseBreakdown(ITEMS[0])).toBe('Main: 30.00\nYard: 12.50');
        expect(warehouseBreakdown(ITEMS[2])).toBe('');
    });

    it('offers every warehouse holding any of the listed items, once and by name', () => {
        expect(warehouseOptions(ITEMS)).toEqual([
            { id: 1, name: 'Main' },
            { id: 2, name: 'Yard' },
        ]);
        expect(warehouseOptions([ITEMS[2]])).toEqual([]);
    });

    it('scoping to a warehouse keeps only what is stocked there, at that quantity', () => {
        const yard = scopeToWarehouse(ITEMS, 2);
        expect(yard).toHaveLength(1);
        expect(yard[0].item_name).toBe('Steel Plate 10mm');
        // The total was 42.5 across two warehouses; the Yard holds 12.5 of it.
        expect(yard[0].quantity).toBe(12.5);
        expect(yard[0].warehouses).toEqual([{ id: 2, name: 'Yard', quantity: 12.5 }]);
    });

    it('leaves the list alone when no warehouse is picked', () => {
        expect(scopeToWarehouse(ITEMS, '')).toBe(ITEMS);
    });

    /**
     * The point of re-scoping: a local shortfall must not stay hidden behind a
     * healthy total held somewhere else, which is what the stock summary flags.
     */
    it('flags a shortfall in one warehouse that the total would have hidden', () => {
        const plate = ITEMS[0];
        expect(isLow(plate)).toBe(false);
        expect(isLow(scopeToWarehouse([plate], 2)[0])).toBe(false);

        const thin = { ...plate, minimum_stock_level: '20.00' };
        expect(isLow(thin)).toBe(false);
        expect(isLow(scopeToWarehouse([thin], 2)[0])).toBe(true);
    });

    it('calls an item low only once it is under a minimum that was actually set', () => {
        expect(isLow({ quantity: 1, minimum_stock_level: '5.00' })).toBe(true);
        expect(isLow({ quantity: 42.5, minimum_stock_level: '10.00' })).toBe(false);
        // Exactly at the minimum is not yet below it.
        expect(isLow({ quantity: 5, minimum_stock_level: '5.00' })).toBe(false);
        // A minimum of 0 means no threshold set, so an unstocked item is silent.
        expect(isLow({ quantity: 0, minimum_stock_level: '0.00' })).toBe(false);
    });
});

describe('ItemTable', () => {
    const renderTable = (rows = ITEMS, handlers = {}) =>
        render(<ItemTable
            items={rows}
            onEdit={handlers.onEdit ?? (() => {})}
            onDelete={handlers.onDelete ?? (() => {})}
        />);

    it('renders every column, with Quantity beside the minimum it is judged against', () => {
        renderTable();
        expect(screen.getAllByRole('columnheader').map((th) => th.textContent)).toEqual([
            'Code', 'Name', 'Category', 'UOM', 'Warehouse', 'Quantity', 'Min Stock', 'Cost Price', 'Status', 'Actions',
        ]);
    });

    /** Two levels, one cell — the table must not grow an eleventh column. */
    it('shows the section under the type badge without a column of its own', () => {
        renderTable();
        expect(screen.getAllByRole('columnheader').map((th) => th.textContent)).not.toContain('Section');
        expect(screen.getByText('Bulk')).toBeInTheDocument();
        expect(screen.getByText('Raw Material')).toHaveClass('badge-blue');
    });

    it('leaves the cell to the badge alone when the item is in no section', () => {
        renderTable([ITEMS[1]]);
        expect(screen.getByText('Finished Good')).toBeInTheDocument();
        expect(screen.queryByText('Bulk')).not.toBeInTheDocument();
    });

    it('collapses several warehouses into one cell but keeps the split on hover', () => {
        renderTable();
        expect(screen.getByText('Main +1')).toHaveAttribute('title', 'Main: 30.00\nYard: 12.50');
        expect(screen.getByText('—')).toBeInTheDocument();
    });

    it('shows on-hand quantity and reddens it only below the minimum', () => {
        renderTable();
        expect(screen.getByText('42.50')).not.toHaveStyle({ color: 'rgb(220, 38, 38)' });
        expect(screen.getByText('1.00')).toHaveStyle({ color: 'rgb(220, 38, 38)' });
    });

    it('spans the new column when there is nothing to show', () => {
        renderTable([]);
        expect(screen.getByText('No items found.')).toHaveAttribute('colspan', '10');
    });

    it('badges category and status rather than printing raw values', () => {
        renderTable();
        expect(screen.getByText('Raw Material')).toHaveClass('badge-blue');
        expect(screen.getByText('WIP')).toHaveClass('badge-yellow');
        expect(screen.getByText('Finished Good')).toHaveClass('badge-green');
        expect(screen.getByText('Inactive')).toHaveClass('badge-gray');
        // Never the Yes/No the earlier React table printed.
        expect(screen.queryByText('Yes')).not.toBeInTheDocument();
    });

    it('right-aligns the stock and cost figures', () => {
        renderTable([ITEMS[0]]);
        expect(screen.getByText('10.00')).toHaveClass('text-right');
        // The cost is an amount, so it carries the symbol and the fils.
        expect(screen.getByText('BD 3.250')).toHaveClass('text-right');
    });

    it('calls back with the row on edit and delete', () => {
        const onEdit = vi.fn();
        const onDelete = vi.fn();
        renderTable([ITEMS[0]], { onEdit, onDelete });

        fireEvent.click(screen.getByText('Edit'));
        fireEvent.click(screen.getByText('Delete'));

        expect(onEdit).toHaveBeenCalledWith(ITEMS[0]);
        expect(onDelete).toHaveBeenCalledWith(ITEMS[0]);
    });

    it('says so when there are none', () => {
        renderTable([]);
        expect(screen.getByText('No items found.')).toBeInTheDocument();
    });
});

describe('ItemToolbar', () => {
    it('offers the four Blade actions with the downloads as real links', () => {
        render(<ItemToolbar onImportClick={() => {}} onCreate={() => {}} />);
        expect(screen.getByText('Export PDF').closest('a')).toHaveAttribute('href', '/api/v1/inventory/items/export-pdf');
        expect(screen.getByText('Template').closest('a')).toHaveAttribute('href', '/api/v1/inventory/items/template');
        expect(screen.getByText('Import Excel')).toBeInTheDocument();
        expect(screen.getByText('+ Add Item')).toHaveClass('btn-primary');
    });

    /** The mobile Actions sheet renders these stacked and without Add. */
    it('omits Add when asked to', () => {
        render(<ItemToolbar onImportClick={() => {}} onCreate={() => {}} stacked showAdd={false} />);
        expect(screen.queryByText('+ Add Item')).not.toBeInTheDocument();
        expect(screen.getByText('Import Excel')).toBeInTheDocument();
    });
});

describe('ItemImportModal', () => {
    const file = () => new File(['x'], 'items.xlsx', {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });

    it('explains which spreadsheet formats are understood', () => {
        render(<ItemImportModal open onClose={() => {}} onImport={() => {}} />);
        expect(screen.getByText(/Forkoll inventory format/)).toBeInTheDocument();
        expect(screen.getByText(/Duplicate names are skipped/)).toBeInTheDocument();
    });

    it('keeps Import disabled until a file is chosen', () => {
        render(<ItemImportModal open onClose={() => {}} onImport={() => {}} />);
        expect(screen.getByText('Import').closest('button')).toBeDisabled();

        fireEvent.change(screen.getByLabelText('Import Excel'), { target: { files: [file()] } });

        expect(screen.getByText('items.xlsx')).toBeInTheDocument();
        expect(screen.getByText('Import').closest('button')).not.toBeDisabled();
    });

    // The Blade modal accepted a dropped file, which the bare input had lost.
    it('accepts a dropped file', () => {
        render(<ItemImportModal open onClose={() => {}} onImport={() => {}} />);
        const dropZone = screen.getByLabelText('Import Excel').closest('label');

        fireEvent.drop(dropZone, { dataTransfer: { files: [file()] } });

        expect(screen.getByText('items.xlsx')).toBeInTheDocument();
    });

    it('hands the chosen file to onImport', async () => {
        const onImport = vi.fn().mockResolvedValue(undefined);
        render(<ItemImportModal open onClose={() => {}} onImport={onImport} />);

        fireEvent.change(screen.getByLabelText('Import Excel'), { target: { files: [file()] } });
        fireEvent.click(screen.getByText('Import'));

        await waitFor(() => expect(onImport).toHaveBeenCalled());
        expect(onImport.mock.calls[0][0].name).toBe('items.xlsx');
    });
});
