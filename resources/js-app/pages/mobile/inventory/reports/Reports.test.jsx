import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import StockSummaryPage from './StockSummaryPage';
import LowStockPage from './LowStockPage';
import ValuationPage from './ValuationPage';
import { ToastProvider } from '../../../../components/ui/Toast';
import * as client from '../../../../api/client';

vi.mock('../../../../echo', () => ({
    echo: { private: () => ({ listen: () => ({ listen: () => {} }) }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const wrap = (ui) => render(<ToastProvider>{ui}</ToastProvider>);

const SUMMARY_ROWS = [
    { id: 1, item_code: 'ITEM-1', item_name: 'Rod', warehouse_name: 'Main', quantity: '4', unit_of_measure: 'PCS' },
    { id: 2, item_code: 'ITEM-2', item_name: 'Widget', warehouse_name: 'Yard', quantity: '9', unit_of_measure: 'BOX' },
];

describe('mobile inventory reports', () => {
    beforeEach(() => vi.restoreAllMocks());

    it('renders report rows as cards, never a table', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: SUMMARY_ROWS, meta: { total_lines: 2 } });
        const { container } = wrap(<StockSummaryPage />);
        expect(await screen.findByText('Rod')).toBeInTheDocument();
        expect(container.querySelector('table')).toBeNull();
    });

    it('filters client-side with a live line count', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: SUMMARY_ROWS, meta: { total_lines: 2 } });
        wrap(<StockSummaryPage />);
        await screen.findByText('Rod');
        expect(screen.getByText('2 lines')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Search inventory summary'), { target: { value: 'widget' } });

        expect(screen.getByText('1 of 2 lines')).toBeInTheDocument();
        expect(screen.queryByText('Rod')).not.toBeInTheDocument();
    });

    it('shows a no-results message rather than an empty screen', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: SUMMARY_ROWS, meta: { total_lines: 2 } });
        wrap(<StockSummaryPage />);
        await screen.findByText('Rod');
        fireEvent.change(screen.getByLabelText('Search inventory summary'), { target: { value: 'zzz' } });
        expect(screen.getByText('No inventory summary match that search.')).toBeInTheDocument();
    });

    it('low stock shows the shortfall on each card', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 1, item_code: 'ITEM-1', item_name: 'Rod', warehouse_name: 'Main', quantity: '4', minimum_stock_level: '10', shortfall: 6 }],
            meta: { below_minimum: 1 },
        });
        wrap(<LowStockPage />);
        expect(await screen.findByText('-6')).toBeInTheDocument();
        expect(screen.getByText('On hand 4 · minimum 10')).toBeInTheDocument();
    });

    it('valuation shows the total value', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 1, item_code: 'ITEM-1', item_name: 'Rod', warehouse_name: 'Main', quantity: '4', cost_price: '2.5', valuation: '10' }],
            meta: { total_valuation: 310 },
        });
        wrap(<ValuationPage />);
        expect(await screen.findByText('310.00')).toBeInTheDocument();
    });
});
