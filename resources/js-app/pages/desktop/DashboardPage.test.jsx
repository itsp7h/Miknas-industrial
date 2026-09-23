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
        expect(await screen.findByText('BD 3,580')).toBeInTheDocument();
        expect(screen.getByText('3')).toBeInTheDocument();
    });

    it('shows every visible KPI label', async () => {
        renderPage();
        await screen.findByText('BD 3,580');
        ['Inventory Value', 'Purchase Pipeline']
            .forEach((label) => expect(screen.getByText(label)).toBeInTheDocument());
    });

    /** Parked with `hidden: true`, so the endpoint may still send them. */
    it('leaves out the sales figures the dashboard no longer carries', async () => {
        renderPage();
        await screen.findByText('BD 3,580');

        expect(screen.queryByText('Total Sales')).not.toBeInTheDocument();
        expect(screen.queryByText('Receivables')).not.toBeInTheDocument();
        expect(screen.queryByText('BD 25,651')).not.toBeInTheDocument();
        expect(screen.queryByText('BD 10,151')).not.toBeInTheDocument();
    });

    it('renders a KPI figure in slate', async () => {
        renderPage();
        expect(await screen.findByText('BD 3,580')).toHaveClass('text-slate-800');
    });

    it('links the Purchase Pipeline card at the React board', async () => {
        renderPage();
        await screen.findByText('BD 3,580');
        expect(screen.getByText('Purchase Pipeline').closest('a'))
            .toHaveAttribute('href', '/app/purchase/pipeline');
    });

    it('renders the visible quick actions and module cards', async () => {
        renderPage();
        await screen.findByText('BD 3,580');
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
        await screen.findByText('BD 3,580');

        ['Production Active', 'New Sales Order', 'New Production Order', 'Production Orders', 'Customers']
            .forEach((label) => expect(screen.queryByText(label)).not.toBeInTheDocument());
        expect(screen.queryByRole('heading', { name: 'Production' })).not.toBeInTheDocument();
        expect(screen.queryByRole('heading', { name: 'Sales' })).not.toBeInTheDocument();
    });

    // The KPI row is xl:grid-cols-5 with all five showing; hiding one has to
    // narrow it, or the row keeps an empty fifth column at xl.
    it('narrows the KPI row to the number of cards actually shown', async () => {
        renderPage();
        // Two left of the five, with Production, Total Sales and Receivables
        // all parked. The row re-widens on its own if any come back.
        const row = (await screen.findByText('BD 3,580')).closest('.grid');
        expect(row).toHaveClass('xl:grid-cols-2');
    });

    /**
     * Links into still-Blade pages must be real anchors so the browser
     * navigates; React Router does not own those URLs. Router links would
     * render a dead in-app route instead.
     */
    it('uses a real anchor for Blade destinations and a router link for /app ones', async () => {
        renderPage();
        await screen.findByText('BD 3,580');

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
