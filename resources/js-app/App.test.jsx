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
    it('renders the SteelERP app shell', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ suppliers_total: 0 });

        render(
            <MemoryRouter initialEntries={['/app']}>
                <ToastProvider>
                    <App currentUserId={1} />
                </ToastProvider>
            </MemoryRouter>
        );

        expect(screen.getByText('SteelERP')).toBeInTheDocument();
    });
});
