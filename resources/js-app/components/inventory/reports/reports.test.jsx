import { describe, it, expect } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import ReportTable from './ReportTable';
import { LOW_STOCK_COLUMNS, lowStockRowClassName } from './lowStockColumns';

// The stock summary used to be this table's stand-in here; it is gone, so the
// low-stock report stands in instead. Every row of that one is below its
// minimum by definition, which is what exercises the per-row emphasis.
const ROWS = [
    {
        id: 1, item_code: 'RM-1001', item_name: 'Steel Plate 10mm', category: 'raw_material',
        warehouse_name: 'Main Warehouse', quantity: '400.00', minimum_stock_level: '450.00', shortfall: 50,
    },
    {
        id: 2, item_code: 'RM-1004', item_name: 'Welding Rod E6013', category: 'raw_material',
        warehouse_name: 'Main Warehouse', quantity: '35.00', minimum_stock_level: '50.00', shortfall: 15,
    },
];

const renderSummary = (rows = ROWS) =>
    render(
        <ReportTable
            columns={LOW_STOCK_COLUMNS}
            rows={rows}
            noun="lines"
            rowClassName={lowStockRowClassName}
            emptyMessage="No stock data available."
        />
    );

describe('ReportTable', () => {
    it('renders the columns it is handed, in order', () => {
        renderSummary();
        expect(screen.getAllByRole('columnheader').map((th) => th.textContent)).toEqual([
            'Item Code', 'Item Name', 'Category', 'Warehouse', 'Current Qty', 'Min Stock', 'Shortage', 'Status',
        ]);
    });

    it('right-aligns the columns that ask for it', () => {
        renderSummary([ROWS[0]]);
        expect(screen.getByText('400.00')).toHaveClass('text-right');
        expect(screen.getByText('450.00')).toHaveClass('text-right');
    });

    /**
     * Why this table exists at all: the shared `Table` primitive cannot express
     * per-row emphasis, and these reports are nothing without it.
     */
    it('applies the row class the caller supplies, and badges the row', () => {
        renderSummary();
        expect(screen.getByText('Welding Rod E6013').closest('tr')).toHaveClass('bg-red-50');
        expect(screen.getAllByText('LOW STOCK')).toHaveLength(2);
    });

    it('reddens the figures, not just the row background', () => {
        renderSummary([ROWS[1]]);
        expect(screen.getByText('35.00')).toHaveClass('text-red-700');
        expect(screen.getByText('RM-1004')).toHaveClass('text-red-700');
    });

    it('filters client-side with a live count', () => {
        renderSummary();
        expect(screen.getByText('2 lines')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Search lines'), { target: { value: 'welding' } });

        expect(screen.getByText('1 of 2 lines')).toBeInTheDocument();
        expect(screen.queryByText('Steel Plate 10mm')).not.toBeInTheDocument();
    });

    it('says so when a search matches nothing', () => {
        renderSummary();
        fireEvent.change(screen.getByLabelText('Search lines'), { target: { value: 'zzz' } });
        expect(screen.getByText('No lines match that search.')).toBeInTheDocument();
    });

    it('shows the empty message when there are no rows at all', () => {
        renderSummary([]);
        expect(screen.getByText('No stock data available.')).toBeInTheDocument();
    });
});
