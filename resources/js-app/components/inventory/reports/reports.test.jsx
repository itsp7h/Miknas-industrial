import { describe, it, expect } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import ReportTable from './ReportTable';
import { SUMMARY_COLUMNS, summaryRowClassName } from './summaryColumns';

const ROWS = [
    {
        id: 1, item_code: 'RM-1001', item_name: 'Steel Plate 10mm', warehouse_name: 'Main Warehouse',
        quantity: '400.00', minimum_stock_level: '10.00', is_low: false,
    },
    {
        id: 2, item_code: 'RM-1004', item_name: 'Welding Rod E6013', warehouse_name: 'Main Warehouse',
        quantity: '35.00', minimum_stock_level: '50.00', is_low: true,
    },
];

const renderSummary = (rows = ROWS) =>
    render(
        <ReportTable
            columns={SUMMARY_COLUMNS}
            rows={rows}
            noun="lines"
            rowClassName={summaryRowClassName}
            emptyMessage="No stock data available."
        />
    );

describe('ReportTable', () => {
    it('renders the Blade summary columns', () => {
        renderSummary();
        expect(screen.getAllByRole('columnheader').map((th) => th.textContent)).toEqual([
            'Item Code', 'Item Name', 'Warehouse', 'Quantity', 'Min Stock',
        ]);
    });

    it('right-aligns the quantity and minimum columns', () => {
        renderSummary([ROWS[0]]);
        expect(screen.getByText('400.00')).toHaveClass('text-right');
        expect(screen.getByText('10.00')).toHaveClass('text-right');
    });

    /**
     * The whole point of the report: a line below its minimum is painted red
     * end to end and badged LOW. The shared `Table` primitive cannot express
     * per-row emphasis, which is why this table exists.
     */
    it('paints a below-minimum row red and badges it LOW', () => {
        renderSummary();
        const lowRow = screen.getByText('Welding Rod E6013').closest('tr');
        expect(lowRow).toHaveClass('bg-red-50');
        expect(screen.getByText('LOW')).toBeInTheDocument();
    });

    it('leaves a healthy row unemphasised and unbadged', () => {
        renderSummary([ROWS[0]]);
        expect(screen.getByText('Steel Plate 10mm').closest('tr')).not.toHaveClass('bg-red-50');
        expect(screen.queryByText('LOW')).not.toBeInTheDocument();
    });

    it('reddens the figures on a low row, not just its background', () => {
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
