import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import LowStockPage from './LowStockPage';
import ValuationPage from './ValuationPage';
import { ToastProvider } from '../../../../components/ui/Toast';
import * as client from '../../../../api/client';

vi.mock('../../../../echo', () => ({
    echo: { private: () => ({ listen: () => ({ listen: () => {} }) }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const wrap = (ui) => render(<ToastProvider>{ui}</ToastProvider>);

// These three cover MobileReport itself — cards rather than a table, a live
// count, a no-results message. The stock summary used to be the stand-in; it is
// gone, so the valuation report stands in.
const VALUATION_ROWS = [
    { id: 1, item_code: 'ITEM-1', item_name: 'Rod', category: 'raw_material', total_qty: '4', cost_price: '2', total_value: '8' },
    { id: 2, item_code: 'ITEM-2', item_name: 'Widget', category: 'finished_good', total_qty: '9', cost_price: '3', total_value: '27' },
];

describe('mobile inventory reports', () => {
    beforeEach(() => vi.restoreAllMocks());

    it('renders report rows as cards, never a table', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: VALUATION_ROWS, meta: { total_valuation: 35 } });
        const { container } = wrap(<ValuationPage />);
        expect(await screen.findByText('Rod')).toBeInTheDocument();
        expect(container.querySelector('table')).toBeNull();
    });

    it('filters client-side with a live line count', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: VALUATION_ROWS, meta: { total_valuation: 35 } });
        wrap(<ValuationPage />);
        await screen.findByText('Rod');
        expect(screen.getByText('2 lines')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Search inventory valuation'), { target: { value: 'widget' } });

        expect(screen.getByText('1 of 2 lines')).toBeInTheDocument();
        expect(screen.queryByText('Rod')).not.toBeInTheDocument();
    });

    it('shows a no-results message rather than an empty screen', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: VALUATION_ROWS, meta: { total_valuation: 35 } });
        wrap(<ValuationPage />);
        await screen.findByText('Rod');
        fireEvent.change(screen.getByLabelText('Search inventory valuation'), { target: { value: 'zzz' } });
        expect(screen.getByText('No inventory valuation match that search.')).toBeInTheDocument();
    });

    it('low stock shows current, minimum and shortage on each card', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 1, item_code: 'ITEM-1', item_name: 'Rod', category: 'raw_material', warehouse_name: 'Main', quantity: '4', minimum_stock_level: '10', shortfall: 6 }],
            meta: { below_minimum: 1 },
        });
        wrap(<LowStockPage />);
        await screen.findByText('Rod');
        expect(screen.getByText('4.00')).toBeInTheDocument();
        expect(screen.getByText('10.00')).toBeInTheDocument();
        expect(screen.getByText('6.00')).toBeInTheDocument();
        expect(screen.getByText('LOW STOCK')).toHaveClass('badge-red');
        expect(screen.getByText(/1 item\(s\) are below minimum stock level/)).toBeInTheDocument();
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
