import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import ProductionOrderListPage from './ProductionOrderListPage';
import ProductionOutputListPage from './ProductionOutputListPage';
import BomListPage from './BomListPage';
import MaterialIssueListPage from './MaterialIssueListPage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => ({ listen: () => {} }), stopListening: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const wrap = (ui) => render(<MemoryRouter><ToastProvider>{ui}</ToastProvider></MemoryRouter>);

const ORDERS = [
    { id: 1, order_number: 'PO-00001', product_name: 'Frame', quantity_to_produce: '10.00', quantity_produced: '4.00', production_date: '2026-08-01', status: 'in_progress' },
    { id: 2, order_number: 'PO-00002', product_name: 'Panel', quantity_to_produce: '5.00', quantity_produced: '0.00', production_date: '2026-08-02', status: 'planned' },
];

describe('mobile production pages', () => {
    beforeEach(() => vi.restoreAllMocks());

    it('renders orders as cards with progress, not a table', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: ORDERS });
        const { container } = wrap(<ProductionOrderListPage />);
        await screen.findByText('PO-00001');
        expect(container.querySelector('table')).toBeNull();
        expect(screen.getByText(/4\.00 of 10\.00 produced/)).toBeInTheDocument();
    });

    it('filters client-side with a live count', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: ORDERS });
        wrap(<ProductionOrderListPage />);
        await screen.findByText('PO-00001');
        expect(screen.getByText('2 orders')).toBeInTheDocument();
        fireEvent.change(screen.getByLabelText('Search production orders'), { target: { value: 'panel' } });
        expect(screen.getByText('1 of 2 orders')).toBeInTheDocument();
        expect(screen.queryByText('PO-00001')).not.toBeInTheDocument();
    });

    it('can search orders by status label', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: ORDERS });
        wrap(<ProductionOrderListPage />);
        await screen.findByText('PO-00001');
        fireEvent.change(screen.getByLabelText('Search production orders'), { target: { value: 'in progress' } });
        expect(screen.getByText('PO-00001')).toBeInTheDocument();
        expect(screen.queryByText('PO-00002')).not.toBeInTheDocument();
    });

    it('bom lines render as cards grouped under their product, not a table', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [
                { id: 1, product_id: 1, product_name: 'Frame', product_code: 'FG-1', raw_material_name: 'Steel Bar', quantity_required: '2.50', unit_of_measure: 'KG' },
                { id: 2, product_id: 2, product_name: 'Panel', product_code: 'FG-2', raw_material_name: 'Steel Sheet', quantity_required: '1.00', unit_of_measure: 'SQM' },
            ],
        });
        const { container } = wrap(<BomListPage />);

        await screen.findByText('Frame');
        expect(container.querySelector('table')).toBeNull();
        expect(screen.getByText('FG-1')).toBeInTheDocument();
        expect(screen.getByText('2.50 KG')).toBeInTheDocument();
        expect(screen.getByText('2 entries')).toBeInTheDocument();
    });

    it('material issues render as cards with a live count', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 1, issue_number: 'MI-00001', production_order_id: 4, issue_date: '2026-08-03', production_order_number: 'PO-00001', item_name: 'Steel Bar', warehouse_name: 'Main', quantity: '20.00' }],
        });
        const { container } = wrap(<MaterialIssueListPage />);

        await screen.findByText('Steel Bar');
        expect(container.querySelector('table')).toBeNull();
        expect(screen.getByText('1 issues')).toBeInTheDocument();
        expect(screen.getByText('MI-00001')).toBeInTheDocument();
        expect(screen.getByText('03 Aug 2026')).toBeInTheDocument();
    });

    // Inline under the table on desktop; on a phone that sits below every issue,
    // so it opens as a sheet.
    it('material issues open the create form as a sheet', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });
        wrap(<MaterialIssueListPage />);

        expect(await screen.findByText('No material issues found.')).toBeInTheDocument();
        expect(screen.queryByText('Issue New Material')).not.toBeInTheDocument();
        fireEvent.click(screen.getByText('+ Issue Material'));
        expect(await screen.findByText('Issue New Material')).toBeInTheDocument();
    });

    it('production output renders as cards and opens its form as a sheet', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 2, production_order_id: 1, production_order_number: 'PO-00001', item_name: 'Frame', warehouse_name: 'Main', quantity: '4.00', output_date: '2026-08-04' }],
        });
        const { container } = wrap(<ProductionOutputListPage />);

        await screen.findByText('Frame');
        expect(container.querySelector('table')).toBeNull();
        expect(screen.getByText('1 entries')).toBeInTheDocument();
        expect(screen.getByText('04 Aug 2026')).toBeInTheDocument();
        fireEvent.click(screen.getByText('+ Record Output'));
        expect(await screen.findByText('Record Production Output')).toBeInTheDocument();
    });
});
