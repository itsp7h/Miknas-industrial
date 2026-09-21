import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import DashboardPage from './DashboardPage';
import { ToastProvider } from '../../components/ui/Toast';
import * as client from '../../api/client';
import { KPIS } from '../../components/dashboard/data';

vi.mock('../../echo', () => ({
    echo: { private: () => ({ listen: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const SUMMARY = {
    suppliers_total: 3,
    total_sales: 25650.75,
    inventory_value: 3580,
    production_in_progress: 2,
    purchase_pending: 3,
    outstanding_receivables: 10150.75,
};

const renderPage = () =>
    render(
        <ToastProvider>
            <MemoryRouter><DashboardPage currentUserId={1} userName="Admin User" /></MemoryRouter>
        </ToastProvider>
    );

describe('desktop DashboardPage', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue(SUMMARY);
    });

    it('greets the user by name', async () => {
        renderPage();
        expect(await screen.findByText(/Welcome back, Admin User/)).toBeInTheDocument();
    });

    // The Blade page rendered number_format($v, 0) — thousands separated, no decimals.
    it('formats every KPI the way the Blade dashboard did', async () => {
        renderPage();
        expect(await screen.findByText('BD 25,651')).toBeInTheDocument();
        expect(screen.getByText('BD 3,580')).toBeInTheDocument();
        expect(screen.getByText('3')).toBeInTheDocument();
        expect(screen.getByText('BD 10,151')).toBeInTheDocument();
    });

    it('shows every visible KPI label', async () => {
        renderPage();
        await screen.findByText('BD 25,651');
        ['Total Sales', 'Inventory Value', 'Purchase Pipeline', 'Receivables']
            .forEach((label) => expect(screen.getByText(label)).toBeInTheDocument());
    });

    // Receivables is the one figure the Blade page coloured red.
    it('renders receivables in red and the rest in slate', async () => {
        renderPage();
        expect(await screen.findByText('BD 10,151')).toHaveClass('text-red-600');
        expect(screen.getByText('BD 25,651')).toHaveClass('text-slate-800');
    });

    it('links the Purchase Pipeline card at the React board', async () => {
        renderPage();
        await screen.findByText('BD 25,651');
        expect(screen.getByText('Purchase Pipeline').closest('a'))
            .toHaveAttribute('href', '/app/purchase/pipeline');
    });

    it('renders the visible quick actions and module cards', async () => {
        renderPage();
        await screen.findByText('BD 25,651');
        ['New Purchase Request', 'Low Stock Alert']
            .forEach((label) => expect(screen.getByText(label)).toBeInTheDocument());
        ['Purchase', 'Inventory']
            .forEach((title) => expect(screen.getByRole('heading', { name: title })).toBeInTheDocument());
    });

    // Production and Sales carry `hidden: true` in dashboard/data.js, the same
    // flag their sidebar groups carry — nothing on the home page should offer a
    // way into a module that is not in use yet.
    it('offers no route into the hidden modules', async () => {
        renderPage();
        await screen.findByText('BD 25,651');

        ['Production Active', 'New Sales Order', 'New Production Order', 'Production Orders', 'Customers']
            .forEach((label) => expect(screen.queryByText(label)).not.toBeInTheDocument());
        expect(screen.queryByRole('heading', { name: 'Production' })).not.toBeInTheDocument();
        expect(screen.queryByRole('heading', { name: 'Sales' })).not.toBeInTheDocument();
    });

    // The KPI row is xl:grid-cols-5 with all five showing; hiding one has to
    // narrow it, or the row keeps an empty fifth column at xl.
    it('narrows the KPI row to the number of cards actually shown', async () => {
        renderPage();
        const row = (await screen.findByText('BD 25,651')).closest('.grid');
        expect(row).toHaveClass('xl:grid-cols-4');
    });

    /**
     * Links into still-Blade pages must be real anchors so the browser
     * navigates; React Router does not own those URLs. Router links would
     * render a dead in-app route instead.
     */
    it('uses a real anchor for Blade destinations and a router link for /app ones', async () => {
        renderPage();
        await screen.findByText('BD 25,651');

        const blade = screen.getByText('Purchase Requests').closest('a');
        expect(blade).toHaveAttribute('href', '/purchase/requests');

        const react = screen.getByText('Raw Materials').closest('a');
        expect(react).toHaveAttribute('href', '/app/inventory/items');
    });

    it('shows a dash instead of a zero before the figures arrive', () => {
        vi.spyOn(client, 'apiGet').mockReturnValue(new Promise(() => {}));
        renderPage();
        expect(screen.getAllByText('—').length).toBe(KPIS.length);
    });
});
