import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
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

describe('desktop SalesOrderListPage', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockImplementation((path) =>
            path === '/sales/orders'
                ? Promise.resolve({ data: ORDERS })
                : Promise.resolve({ customers: [], items: [], statuses: [] })
        );
    });

    it('lists orders with money and status labels', async () => {
        renderPage();
        expect(await screen.findByText('SO-00001')).toBeInTheDocument();
        expect(screen.getByText('250.50')).toBeInTheDocument();
        expect(screen.getByText('Confirmed')).toBeInTheDocument();
    });

    // Blade's six columns, with the date as `d M Y` and badged statuses rather
    // than the coloured text the first React port used.
    it('lists Blade\u2019s six columns, badged and date-formatted', async () => {
        renderPage();
        await screen.findByText('SO-00001');
        expect(screen.getAllByRole('columnheader').map((th) => th.textContent))
            .toEqual(['Order #', 'Customer', 'Date', 'Total', 'Status', 'Actions']);
        expect(screen.getByText('01 Aug 2026')).toBeInTheDocument();
        expect(screen.getByText('Draft')).toHaveClass('badge-gray');
        expect(screen.getByText('Confirmed')).toHaveClass('badge-blue');
    });

    // Blade led the actions with View; the port had dropped it, leaving a
    // confirmed order with no actions at all and no way in from the list.
    it('offers View on every order, whatever its status', async () => {
        renderPage();
        await screen.findByText('SO-00001');
        const views = screen.getAllByText('View');
        expect(views).toHaveLength(2);
        expect(views[0]).toHaveAttribute('href', '/app/sales/orders/1');
    });

    it('says so when there are no orders', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });
        renderPage();
        expect(await screen.findByText('No sales orders found.')).toBeInTheDocument();
    });

    it('filters client-side with a live count', async () => {
        renderPage();
        await screen.findByText('SO-00001');
        expect(screen.getByText('2 orders')).toBeInTheDocument();
        fireEvent.change(screen.getByLabelText('Search sales orders'), { target: { value: 'zenith' } });
        expect(screen.getByText('1 of 2 orders')).toBeInTheDocument();
        expect(screen.queryByText('SO-00001')).not.toBeInTheDocument();
    });

    // A confirmed order is an agreement with the customer: the UI must not
    // offer edit or delete on it, matching the API's 422.
    it('offers edit, confirm and delete only on draft orders', async () => {
        renderPage();
        await screen.findByText('SO-00001');
        expect(screen.getAllByText('Edit')).toHaveLength(1);
        expect(screen.getAllByText('Confirm')).toHaveLength(1);
        expect(screen.getAllByText('Delete')).toHaveLength(1);
    });

    it('asks before confirming and explains the consequence', async () => {
        renderPage();
        await screen.findByText('SO-00001');
        fireEvent.click(screen.getByText('Confirm'));
        expect(await screen.findByText(/can no longer be edited/)).toBeInTheDocument();
    });

    it('patches the confirm endpoint and reflects the new status', async () => {
        const patch = vi.spyOn(client, 'apiPatch').mockResolvedValue({
            data: { ...ORDERS[0], status: 'confirmed' },
        });
        renderPage();
        await screen.findByText('SO-00001');
        fireEvent.click(screen.getByText('Confirm'));
        // Both the row action and the modal button read "Confirm"; the modal's
        // is the one rendered last.
        const buttons = await screen.findAllByRole('button', { name: 'Confirm' });
        fireEvent.click(buttons[buttons.length - 1]);

        await waitFor(() => expect(patch).toHaveBeenCalledWith('/sales/orders/1/confirm'));
    });

    it('surfaces an API refusal as an error toast rather than failing silently', async () => {
        vi.spyOn(client, 'apiPatch').mockRejectedValue({ message: 'Only a draft order can be confirmed.' });
        renderPage();
        await screen.findByText('SO-00001');
        fireEvent.click(screen.getByText('Confirm'));
        const buttons = await screen.findAllByRole('button', { name: 'Confirm' });
        fireEvent.click(buttons[buttons.length - 1]);

        await waitFor(() => {
            expect(screen.getByText('Only a draft order can be confirmed.')).toBeInTheDocument();
        });
    });

    it('fetches the full order before editing, since the list has no line items', async () => {
        const spy = client.apiGet;
        renderPage();
        await screen.findByText('SO-00001');
        fireEvent.click(screen.getByText('Edit'));
        await waitFor(() => expect(spy).toHaveBeenCalledWith('/sales/orders/1'));
    });
});
