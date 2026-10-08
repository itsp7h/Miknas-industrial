import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import PurchaseOrderListPage from './PurchaseOrderListPage';
import { ToastProvider } from '../../../components/ui/Toast';
import { AccessProvider } from '../../../layouts/AccessContext';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => ({ listen: () => {} }), stopListening: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const ORDERS = [
    { id: 1, po_number: 'PO-00001', supplier_name: 'Gulf Metals', po_date: '2026-08-01', total_amount: '600.00', status: 'draft' },
    { id: 2, po_number: 'PO-00002', supplier_name: 'Zenith Supply', po_date: '2026-08-02', total_amount: '250.50', status: 'sent' },
];

const renderPage = ({ permissions = null } = {}) =>
    render(
        <ToastProvider>
            <AccessProvider isAdmin={permissions === null} permissions={permissions ?? []}>
                <MemoryRouter><PurchaseOrderListPage /></MemoryRouter>
            </AccessProvider>
        </ToastProvider>
    );

describe('mobile PurchaseOrderListPage', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockImplementation((path) =>
            path === '/purchase/orders'
                ? Promise.resolve({ data: ORDERS })
                : Promise.resolve({ suppliers: [], items: [], purchase_requests: [], statuses: [] })
        );
    });

    it('renders each order as a card rather than a wide table', async () => {
        renderPage();
        expect(await screen.findByText('PO-00001')).toBeInTheDocument();
        expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });

    // CLAUDE.md gotcha #6: search filters client-side over the full list, with a
    // live count and no request.
    it('filters client-side and shows a live count', async () => {
        renderPage();
        await screen.findByText('PO-00001');
        expect(screen.getByText('2 purchase orders')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Search orders'), { target: { value: 'zenith' } });

        expect(screen.getByText('1 of 2 purchase orders')).toBeInTheDocument();
        expect(screen.queryByText('PO-00001')).not.toBeInTheDocument();
        expect(screen.getByText('PO-00002')).toBeInTheDocument();
    });

    it('says so when a search matches nothing', async () => {
        renderPage();
        await screen.findByText('PO-00001');
        fireEvent.change(screen.getByLabelText('Search orders'), { target: { value: 'nope' } });
        expect(screen.getByText('No purchase orders match that search')).toBeInTheDocument();
    });

    // Editing and deleting moved to the order's own page; a row opens it.
    it('links each order to its page', async () => {
        renderPage();
        expect((await screen.findByText('PO-00001')).closest('a')).toHaveAttribute('href', '/app/purchase/orders/1');
    });

    // CLAUDE.md #14: disabled with the reason, not hidden.
    it('offers New LPO disabled without purchase-orders.create', async () => {
        renderPage({ permissions: ['purchase-orders.view'] });
        await screen.findByText('PO-00001');
        const button = screen.getByRole('button', { name: 'New purchase order' });
        expect(button).toBeDisabled();
        expect(button).toHaveAttribute('title', 'You do not have permission to create purchase orders');
    });
});
