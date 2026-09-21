import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import WarehouseDetailPage from './WarehouseDetailPage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: {
        private: () => ({ listen: () => ({ listen: () => {} }), stopListening: () => {} }),
        channel: () => ({ listen: () => {}, stopListening: () => {} }),
        leave: () => {},
    },
}));

const PAYLOAD = {
    data: { id: 1, code: 'WH-ASKAR', name: 'Ware house 1 Askar', location: 'Askar', latitude: null, longitude: null, is_active: true },
    items: [
        {
            id: 1, item_id: 10, item_code: 'ITEM-00013', item_name: 'Pentaproof 20 P',
            category: 'raw_material', category_path: 'Raw Materials / Chemical Materials',
            item_category_name: 'Chemical Materials', unit_of_measure: 'KG',
            quantity: 4, minimum_stock_level: 10, cost_price: 1.092, total_value: 4.368, is_active: true,
        },
        {
            id: 2, item_id: 11, item_code: 'ITEM-00001', item_name: 'SUPERBOND F5 White',
            category: 'finished_good', category_path: 'Finished Goods',
            item_category_name: null, unit_of_measure: 'BAG',
            quantity: 20, minimum_stock_level: 0, cost_price: 5, total_value: 100, is_active: true,
        },
    ],
    meta: { total_items: 2, raw_material_count: 1, finished_good_count: 1, total_value: 104.368, below_minimum: 1 },
};

const renderPage = () => render(
    <ToastProvider>
        <MemoryRouter initialEntries={['/app/inventory/warehouses/1']}>
            <Routes>
                <Route path="/app/inventory/warehouses/:id" element={<WarehouseDetailPage />} />
            </Routes>
        </MemoryRouter>
    </ToastProvider>
);

describe('desktop WarehouseDetailPage', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue(PAYLOAD);
    });

    it('names the warehouse and fetches its own stock', async () => {
        renderPage();
        expect(await screen.findByText('Ware house 1 Askar')).toBeInTheDocument();
        expect(client.apiGet).toHaveBeenCalledWith('/inventory/warehouses/1');
        expect(screen.getByText('WH-ASKAR')).toBeInTheDocument();
    });

    /**
     * The two item pages keep raw materials and finished goods apart on
     * purpose; a warehouse holds both, so it shows both, split the same way.
     */
    it('splits the stock into raw materials and finished goods', async () => {
        renderPage();
        await screen.findByText('Ware house 1 Askar');

        expect(screen.getByText('Raw Materials')).toBeInTheDocument();
        expect(screen.getByText('Finished Goods')).toBeInTheDocument();
        expect(screen.getByText('Pentaproof 20 P')).toBeInTheDocument();
        expect(screen.getByText('SUPERBOND F5 White')).toBeInTheDocument();
    });

    it('shows quantities bare and values with the currency', async () => {
        renderPage();
        await screen.findByText('Pentaproof 20 P');

        // A count of kilos carries no symbol; the cost and value do.
        expect(screen.getByText('4.00')).toBeInTheDocument();
        expect(screen.getByText('BD 1.092')).toBeInTheDocument();
        // Twice: the row's value, and the section header now totalling that section.
        expect(screen.getAllByText('BD 4.368')).toHaveLength(2);
        expect(screen.getByText('BD 104.368')).toBeInTheDocument();
    });

    it('reddens a line below its minimum and counts it in the header', async () => {
        renderPage();
        await screen.findByText('Pentaproof 20 P');

        expect(screen.getByText('4.00')).toHaveStyle({ color: 'rgb(220, 38, 38)' });
        // 20 BAG against no minimum is not a shortage.
        expect(screen.getByText('20.00')).not.toHaveStyle({ color: 'rgb(220, 38, 38)' });
        expect(screen.getByText('Below minimum')).toBeInTheDocument();
    });

    it('searches across both sections at once', async () => {
        renderPage();
        await screen.findByText('Pentaproof 20 P');

        fireEvent.change(screen.getByLabelText('Search stock'), { target: { value: 'superbond' } });

        await waitFor(() => expect(screen.queryByText('Pentaproof 20 P')).not.toBeInTheDocument());
        expect(screen.getByText('SUPERBOND F5 White')).toBeInTheDocument();
        expect(screen.getByText('1 of 2 items')).toBeInTheDocument();
        expect(screen.getByText('No raw materials match that search.')).toBeInTheDocument();
    });

    it('offers a way back to the list', async () => {
        renderPage();
        await screen.findByText('Ware house 1 Askar');
        expect(screen.getByText('← Warehouses').closest('a')).toHaveAttribute('href', '/app/inventory/warehouses');
    });
    /** The page read as one white sheet; each section is now told apart. */
    it('gives each section its own tinted header with a count and a total', async () => {
        renderPage();
        await screen.findByText('Pentaproof 20 P');

        const raw = screen.getByText('Raw Materials').closest('div');
        const finished = screen.getByText('Finished Goods').closest('div');
        expect(raw).not.toBeNull();
        expect(finished).not.toBeNull();

        // Each section counts only its own lines.
        expect(screen.getAllByText('1 item')).toHaveLength(2);
        // And the low line is called out on the section that holds it.
        expect(screen.getByText('1 low')).toHaveClass('badge-red');
    });

    it('sets the warehouse apart from the tables with its own card', async () => {
        renderPage();
        await screen.findByText('Ware house 1 Askar');

        // The code and status sit with the name, not in a table row.
        expect(screen.getByText('WH-ASKAR')).toBeInTheDocument();
        expect(screen.getByText('Active')).toHaveClass('badge-green');
        expect(screen.getByText('Askar')).toBeInTheDocument();
    });

});
