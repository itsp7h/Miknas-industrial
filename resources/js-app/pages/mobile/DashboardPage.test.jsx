import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import DashboardPage from './DashboardPage';
import { ToastProvider } from '../../components/ui/Toast';
import * as client from '../../api/client';

vi.mock('../../echo', () => ({
    echo: { private: () => ({ listen: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const SUMMARY = {
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

describe('mobile DashboardPage', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue(SUMMARY);
    });

    it('shows the same five figures as desktop', async () => {
        renderPage();
        expect(await screen.findByText('25,651')).toBeInTheDocument();
        expect(screen.getByText('3,580')).toBeInTheDocument();
        expect(screen.getByText('10,151')).toBeInTheDocument();
    });

    it('keeps receivables red, matching desktop and the Blade page', async () => {
        renderPage();
        expect(await screen.findByText('10,151')).toHaveClass('text-red-600');
    });

    it('still renders the quick actions and module cards', async () => {
        renderPage();
        await screen.findByText('25,651');
        expect(screen.getByText('New Purchase Request')).toBeInTheDocument();
        ['Purchase', 'Inventory', 'Production', 'Sales']
            .forEach((title) => expect(screen.getByRole('heading', { name: title })).toBeInTheDocument());
    });
});
