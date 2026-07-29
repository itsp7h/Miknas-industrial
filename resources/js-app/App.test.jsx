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
        vi.spyOn(client, 'apiGet').mockResolvedValue({ suppliers_total: 7 });

        render(
            <MemoryRouter initialEntries={['/app']}>
                <ToastProvider>
                    <App currentUserId={1} />
                </ToastProvider>
            </MemoryRouter>
        );

        await screen.findByText('7');
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
