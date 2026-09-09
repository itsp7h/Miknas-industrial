import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import SalesOrderListPage from './SalesOrderListPage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => ({ listen: () => {} }), stopListening: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const ORDERS = [
    { id: 1, order_number: 'SO-00001', customer_name: 'Gulf Steel', order_date: '2026-08-01', total_amount: '100.00', status: 'draft' },
    { id: 2, order_number: 'SO-00002', customer_name: 'Zenith', order_date: '2026-08-02', total_amount: '250.50', status: 'confirmed' },
];

const renderPage = () =>
    render(
        <ToastProvider>
            <MemoryRouter><SalesOrderListPage /></MemoryRouter>
        </ToastProvider>
    );

describe('mobile SalesOrderListPage', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockImplementation((path) =>
            path === '/sales/orders' ? Promise.resolve({ data: ORDERS }) : Promise.resolve({ customers: [], items: [] })
        );
    });

    it('renders orders as cards, not a table', async () => {
        const { container } = renderPage();
        expect(await screen.findByText('SO-00001')).toBeInTheDocument();
        expect(container.querySelector('table')).toBeNull();
    });

    it('filters client-side with a live count', async () => {
        renderPage();
        await screen.findByText('SO-00001');
        expect(screen.getByText('2 orders')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Search sales orders'), { target: { value: 'zenith' } });

        expect(screen.getByText('1 of 2 orders')).toBeInTheDocument();
        expect(screen.queryByText('SO-00001')).not.toBeInTheDocument();
    });

    it('can search by status label', async () => {
        renderPage();
        await screen.findByText('SO-00001');
        fireEvent.change(screen.getByLabelText('Search sales orders'), { target: { value: 'confirmed' } });
        expect(screen.getByText('SO-00002')).toBeInTheDocument();
        expect(screen.queryByText('SO-00001')).not.toBeInTheDocument();
    });

    it('hides draft-only actions on a confirmed order', async () => {
        renderPage();
        await screen.findByText('SO-00001');
        expect(screen.getAllByText('Edit')).toHaveLength(1);
        expect(screen.getAllByText('Delete')).toHaveLength(1);
    });

    it('shows a no-results message', async () => {
        renderPage();
        await screen.findByText('SO-00001');
        fireEvent.change(screen.getByLabelText('Search sales orders'), { target: { value: 'zzz' } });
        expect(screen.getByText('No sales orders match that search.')).toBeInTheDocument();
    });
});
