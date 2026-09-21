import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { ToastProvider } from './components/ui/Toast';
import * as client from './api/client';
import App from './App';

// `useLiveList` chains `.listen(...).listen(...)` and calls `stopListening` on
// unmount, so the stub has to be shaped like a real channel, not just callable.
vi.mock('./echo', () => ({
    echo: {
        private: () => ({ listen: () => ({ listen: () => {} }), stopListening: () => {} }),
        channel: () => ({ listen: () => {}, stopListening: () => {} }),
        leave: () => {},
    },
}));

describe('App', () => {
    it('routes /app to the dashboard page', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            total_sales: 25650.75,
            inventory_value: 3580,
            production_in_progress: 2,
            purchase_pending: 3,
            outstanding_receivables: 10150.75,
        });

        render(
            <MemoryRouter initialEntries={['/app']}>
                <ToastProvider>
                    <App currentUserId={1} userName="Admin User" isAdmin />
                </ToastProvider>
            </MemoryRouter>
        );

        // A KPI figure only the dashboard renders.
        await screen.findByText('BD 25,651');
    });

    /**
     * The stock summary page is gone, but its URL sat in the sidebar long
     * enough to be bookmarked, and a dead end is worse than a hop. It lands on
     * Raw Materials, which answers the same question.
     */
    it('redirects the deleted stock summary URL to Raw Materials', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });

        render(
            <MemoryRouter initialEntries={['/app/inventory/reports/summary']}>
                <ToastProvider>
                    <App currentUserId={1} userName="Admin User" isAdmin />
                </ToastProvider>
            </MemoryRouter>
        );

        expect(await screen.findByText('Raw Materials', { selector: 'h1' })).toBeInTheDocument();
        expect(screen.queryByText('Page not found.')).not.toBeInTheDocument();
    });

    /** VAT had its own page and menu entry; both are Finance now. */
    it('redirects the old VAT settings URL to Finance', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            vat_rate: 10, currency_code: 'BHD', currency_symbol: 'BD', currencies: [],
        });

        render(
            <MemoryRouter initialEntries={['/app/settings/vat']}>
                <ToastProvider>
                    <App currentUserId={1} userName="Admin User" isAdmin />
                </ToastProvider>
            </MemoryRouter>
        );

        expect(await screen.findByText('Finance', { selector: 'h1' })).toBeInTheDocument();
        expect(screen.queryByText('Page not found.')).not.toBeInTheDocument();
    });

    it('routes an unknown /app/* path to a not-found message', () => {
        render(
            <MemoryRouter initialEntries={['/app/does-not-exist']}>
                <ToastProvider>
                    <App currentUserId={1} isAdmin />
                </ToastProvider>
            </MemoryRouter>
        );

        expect(screen.getByText('Page not found.')).toBeInTheDocument();
    });
    /**
     * Hiding a tab from the menu is not the same as closing it. Someone who
     * knows the URL used to land on a page that rendered normally and then
     * failed every fetch — an empty table reads as "nothing here", not "not
     * yours".
     */
    it('refuses a page the user has no permission for, by URL', () => {
        render(
            <MemoryRouter initialEntries={['/app/inventory/items']}>
                <ToastProvider>
                    <App currentUserId={1} userName="Mo" permissions={['pipeline.view']} />
                </ToastProvider>
            </MemoryRouter>
        );

        expect(screen.getByText('Not your page')).toBeInTheDocument();
        expect(screen.queryByText('Raw Materials', { selector: 'h1' })).not.toBeInTheDocument();
    });

    it('lets the same user reach the one page they were granted', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [], meta: {} });

        render(
            <MemoryRouter initialEntries={['/app/purchase/pipeline']}>
                <ToastProvider>
                    <App currentUserId={1} userName="Mo" permissions={['pipeline.view']} />
                </ToastProvider>
            </MemoryRouter>
        );

        expect(screen.queryByText('Not your page')).not.toBeInTheDocument();
    });

    /** Users and Integrations carry no permission name, so nothing grants them. */
    it('keeps Users out of reach of a non-admin however they are granted', () => {
        render(
            <MemoryRouter initialEntries={['/app/settings/users']}>
                <ToastProvider>
                    <App currentUserId={1} userName="Mo" permissions={['users.view', 'users.edit']} />
                </ToastProvider>
            </MemoryRouter>
        );

        expect(screen.getByText('Not your page')).toBeInTheDocument();
    });

});
