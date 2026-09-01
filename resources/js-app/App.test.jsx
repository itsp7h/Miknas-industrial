import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { ToastProvider } from './components/ui/Toast';
import * as client from './api/client';
import App from './App';

vi.mock('./echo', () => ({
    echo: { private: () => ({ listen: () => {} }), leave: () => {} },
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
                    <App currentUserId={1} userName="Admin User" />
                </ToastProvider>
            </MemoryRouter>
        );

        // A KPI figure only the dashboard renders.
        await screen.findByText('25,651');
    });

    it('routes an unknown /app/* path to a not-found message', () => {
        render(
            <MemoryRouter initialEntries={['/app/does-not-exist']}>
                <ToastProvider>
                    <App currentUserId={1} />
                </ToastProvider>
            </MemoryRouter>
        );

        expect(screen.getByText('Page not found.')).toBeInTheDocument();
    });
});
