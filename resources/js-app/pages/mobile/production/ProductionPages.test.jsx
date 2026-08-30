import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import ProductionOrderListPage from './ProductionOrderListPage';
import FlowListPage from './FlowListPage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => ({ listen: () => {} }), stopListening: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const wrap = (ui) => render(<ToastProvider>{ui}</ToastProvider>);

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
        expect(screen.getByText(/4 of 10 made/)).toBeInTheDocument();
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

    it('material issues render as cards with a live count', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 1, issue_number: 'MI-00001', issue_date: '2026-08-03', production_order_number: 'PO-00001', item_name: 'Steel Bar', warehouse_name: 'Main', quantity: '20.00' }],
        });
        const { container } = wrap(<FlowListPage kind="material-issue" />);
        await screen.findByText('Steel Bar');
        expect(container.querySelector('table')).toBeNull();
        expect(screen.getByText('1 issues')).toBeInTheDocument();
    });
});
