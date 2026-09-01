import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import StockSummaryPage from './StockSummaryPage';
import LowStockPage from './LowStockPage';
import ValuationPage from './ValuationPage';
import MovementReportPage from './MovementReportPage';
import { ToastProvider } from '../../../../components/ui/Toast';
import * as client from '../../../../api/client';

vi.mock('../../../../echo', () => ({
    echo: { private: () => ({ listen: () => ({ listen: () => {} }) }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const wrap = (ui) => render(<ToastProvider>{ui}</ToastProvider>);

describe('desktop inventory reports', () => {
    beforeEach(() => vi.restoreAllMocks());

    it('summary shows the stock-line count and the rows', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 1, item_code: 'ITEM-1', item_name: 'Rod', warehouse_name: 'Main', quantity: '4', unit_of_measure: 'PCS' }],
            meta: { total_lines: 1 },
        });
        wrap(<StockSummaryPage />);
        expect(await screen.findByText('Rod')).toBeInTheDocument();
        expect(screen.getByText('Stock lines')).toBeInTheDocument();
    });

    /**
     * The Blade report led with a red banner naming the count and the urgency,
     * which replaces the generic "Below minimum" strip, and badged every row —
     * they are all below minimum by definition.
     */
    it('low stock leads with the red banner and badges every row', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 1, item_code: 'ITEM-1', item_name: 'Rod', category: 'raw_material', warehouse_name: 'Main', quantity: '4', minimum_stock_level: '10', shortfall: 6 }],
            meta: { below_minimum: 1 },
        });
        wrap(<LowStockPage />);
        expect(await screen.findByText('Rod')).toBeInTheDocument();
        expect(screen.getByText(/1 item\(s\) are below minimum stock level/)).toBeInTheDocument();
        expect(screen.getByText('LOW STOCK')).toHaveClass('badge-red');
        expect(screen.getByText('Rod').closest('tr')).toHaveClass('bg-red-50');
    });

    it('low stock shows the shortage, the minimum and the category', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 1, item_code: 'ITEM-1', item_name: 'Rod', category: 'raw_material', warehouse_name: 'Main', quantity: '4', minimum_stock_level: '10', shortfall: 6 }],
            meta: { below_minimum: 1 },
        });
        wrap(<LowStockPage />);
        await screen.findByText('Rod');
        expect(screen.getByText('6.00')).toHaveClass('text-red-600');
        expect(screen.getByText('10.00')).toBeInTheDocument();
        // Category was not sent by the endpoint at all before this.
        expect(screen.getByText('Raw Material')).toBeInTheDocument();
    });

    it('low stock says so plainly when nothing is below minimum', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [], meta: { below_minimum: 0 } });
        wrap(<LowStockPage />);
        // Blade's wording, printed in green.
        expect(await screen.findByText('All items are above minimum stock levels.')).toBeInTheDocument();
    });

    it('valuation formats money and shows the total', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 1, item_code: 'ITEM-1', item_name: 'Rod', warehouse_name: 'Main', quantity: '4', cost_price: '2.5', valuation: '10' }],
            meta: { total_valuation: 310 },
        });
        wrap(<ValuationPage />);
        expect(await screen.findByText('Rod')).toBeInTheDocument();
        expect(screen.getByText('310.00')).toBeInTheDocument();
        expect(screen.getByText('2.50')).toBeInTheDocument();
    });

    // The date/item filters narrow server-side, so applying them must re-query
    // rather than filter the rows already on screen.
    it('movement report re-queries the API when filters are applied', async () => {
        const spy = vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 1, item_name: 'Rod', warehouse_name: 'Main', type: 'in', quantity: '5', notes: null, created_at: '2026-08-01T10:00:00+00:00' }],
            meta: { items: [{ id: 7, item_code: 'ITEM-1', item_name: 'Rod' }] },
        });
        wrap(<MovementReportPage />);
        // "Rod" appears both as a table cell and as an <option> in the item
        // filter, so anchor on the warehouse cell, which is unique.
        await screen.findByText('Main');

        fireEvent.change(screen.getByLabelText('Item (optional)'), { target: { value: '7' } });
        fireEvent.click(screen.getByText('Filter'));

        await waitFor(() => {
            expect(spy).toHaveBeenCalledWith('/inventory/reports/movement?item_id=7');
        });
    });

    it('movement report clears filters back to an unfiltered query', async () => {
        const spy = vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [], meta: { items: [{ id: 7, item_code: 'ITEM-1', item_name: 'Rod' }] },
        });
        wrap(<MovementReportPage />);
        await screen.findByText('Clear');

        fireEvent.change(screen.getByLabelText('Item (optional)'), { target: { value: '7' } });
        fireEvent.click(screen.getByText('Filter'));
        fireEvent.click(screen.getByText('Clear'));

        await waitFor(() => {
            expect(spy).toHaveBeenLastCalledWith('/inventory/reports/movement');
        });
    });
});
