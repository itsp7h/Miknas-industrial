import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import ProductionOrderDetailPage from './ProductionOrderDetailPage';
import MobileProductionOrderDetailPage from '../../mobile/production/ProductionOrderDetailPage';
import { ToastProvider } from '../../../components/ui/Toast';
import { PageTitleProvider } from '../../../layouts/PageTitleContext';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => {}, stopListening: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const ORDER = {
    id: 7,
    order_number: 'PO-00007',
    product_id: 3,
    product_name: 'Frame',
    quantity_to_produce: '10.00',
    quantity_produced: '4.00',
    outstanding: 6,
    production_date: '2026-08-01',
    completion_date: null,
    status: 'in_progress',
    notes: 'Rush job',
    bom: [{ id: 1, material_name: 'Steel Bar', quantity_required: '2.50', unit_of_measure: 'KG' }],
    material_issues: [{ id: 5, item_name: 'Steel Bar', warehouse_name: 'Main', quantity: '20.00', issue_date: '2026-08-02' }],
    outputs: [{ id: 9, item_name: 'Frame', warehouse_name: 'Main', quantity: '4.00', output_date: '2026-08-03' }],
};

const wrap = (Page) => render(
    <MemoryRouter initialEntries={['/app/production/orders/7']}>
        <PageTitleProvider>
            <ToastProvider>
                <Routes>
                    <Route path="/app/production/orders/:id" element={<Page />} />
                </Routes>
            </ToastProvider>
        </PageTitleProvider>
    </MemoryRouter>
);

describe('production order detail page', () => {
    beforeEach(() => vi.restoreAllMocks());

    // Blade laid these four sections out, but its controller passed only
    // $productionOrder — $bom, $materialIssues and $outputs were never set, so
    // three of the four never rendered.
    it('renders all four sections the Blade page laid out', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: ORDER });
        wrap(ProductionOrderDetailPage);

        expect(await screen.findByText('Order Details')).toBeInTheDocument();
        expect(screen.getByText('Bill of Materials')).toBeInTheDocument();
        expect(screen.getByText('Material Issues')).toBeInTheDocument();
        expect(screen.getByText('Production Output')).toBeInTheDocument();
        expect(screen.getByText('Steel Bar', { selector: 'td.py-2' })).toBeInTheDocument();
        expect(screen.getByText('Rush job')).toBeInTheDocument();
        expect(screen.getByText('01 Aug 2026')).toBeInTheDocument();
    });

    it('offers Mark Complete on an in-progress order and confirms first', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: ORDER });
        const patch = vi.spyOn(client, 'apiPatch').mockResolvedValue({ data: ORDER });
        wrap(ProductionOrderDetailPage);

        fireEvent.click(await screen.findByText('Mark Complete'));
        expect(await screen.findByText(/cannot be reopened/)).toBeInTheDocument();
        fireEvent.click(screen.getByText('Confirm'));
        await waitFor(() => expect(patch).toHaveBeenCalledWith('/production/orders/7/complete'));
    });

    it('offers Start Production only while the order is planned', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: { ...ORDER, status: 'planned' } });
        wrap(ProductionOrderDetailPage);

        expect(await screen.findByText('Start Production')).toBeInTheDocument();
        expect(screen.queryByText('Mark Complete')).not.toBeInTheDocument();
    });

    it('mobile stacks the same sections and pins the action full-width', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: ORDER });
        wrap(MobileProductionOrderDetailPage);

        expect(await screen.findByText('← Production Orders')).toBeInTheDocument();
        expect(screen.getByText('Bill of Materials')).toBeInTheDocument();
        expect(screen.getByText('Mark Complete')).toHaveStyle({ width: '100%' });
    });

    it('says so plainly when the order is not found', async () => {
        vi.spyOn(client, 'apiGet').mockRejectedValue({ message: 'Not found' });
        wrap(ProductionOrderDetailPage);

        expect(await screen.findByText('That production order could not be found.')).toBeInTheDocument();
    });
});
