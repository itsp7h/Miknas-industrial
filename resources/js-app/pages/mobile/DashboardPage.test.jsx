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

    it('shows the same figures as desktop', async () => {
        renderPage();
        expect(await screen.findByText('BD 3,580')).toBeInTheDocument();
        expect(screen.getByText('3')).toBeInTheDocument();
    });

    /** Parked on both, so the two views cannot drift apart on this. */
    it('leaves out the sales figures here too', async () => {
        renderPage();
        await screen.findByText('BD 3,580');

        expect(screen.queryByText('Total Sales')).not.toBeInTheDocument();
        expect(screen.queryByText('Receivables')).not.toBeInTheDocument();
    });

    it('still renders the quick actions and the visible module cards', async () => {
        renderPage();
        await screen.findByText('BD 3,580');
        expect(screen.getByText('New Purchase Request')).toBeInTheDocument();
        ['Purchase', 'Inventory']
            .forEach((title) => expect(screen.getByRole('heading', { name: title })).toBeInTheDocument());
    });

    // Hiding is in the shared data module, so both chromes drop them together.
    it('drops the hidden modules here too', async () => {
        renderPage();
        await screen.findByText('BD 3,580');
        expect(screen.queryByRole('heading', { name: 'Production' })).not.toBeInTheDocument();
        expect(screen.queryByRole('heading', { name: 'Sales' })).not.toBeInTheDocument();
        expect(screen.queryByText('New Sales Order')).not.toBeInTheDocument();
    });
});
