import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import ItemTable from './ItemTable';
import ItemToolbar from './ItemToolbar';
import ItemImportModal from './ItemImportModal';
import { categoryBadgeClass, categoryLabel, num } from './itemStyles';

const ITEMS = [
    {
        id: 1, item_code: 'RM-1001', item_name: 'Steel Plate 10mm', category: 'raw_material',
        unit_of_measure: 'KG', minimum_stock_level: '10.00', cost_price: '3.25', is_active: true,
    },
    {
        id: 2, item_code: 'FG-2001', item_name: 'Widget', category: 'finished_good',
        unit_of_measure: 'PCS', minimum_stock_level: '5.00', cost_price: '40.00', is_active: false,
    },
    {
        id: 3, item_code: 'WIP-1', item_name: 'Half Widget', category: 'wip',
        unit_of_measure: 'PCS', minimum_stock_level: '0.00', cost_price: '20.00', is_active: true,
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
});

describe('ItemTable', () => {
    const renderTable = (rows = ITEMS, handlers = {}) =>
        render(<ItemTable
            items={rows}
            onEdit={handlers.onEdit ?? (() => {})}
            onDelete={handlers.onDelete ?? (() => {})}
        />);

    it('renders all eight Blade columns', () => {
        renderTable();
        expect(screen.getAllByRole('columnheader').map((th) => th.textContent)).toEqual([
            'Code', 'Name', 'Category', 'UOM', 'Min Stock', 'Cost Price', 'Status', 'Actions',
        ]);
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
        expect(screen.getByText('3.25')).toHaveClass('text-right');
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
