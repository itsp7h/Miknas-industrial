import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { ToastProvider } from '../components/ui/Toast';
import * as client from '../api/client';
import DashboardPage from './DashboardPage';

vi.mock('../echo', () => ({
    echo: { private: () => ({ listen: () => {} }), leave: () => {} },
}));

describe('DashboardPage', () => {
    beforeEach(() => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ suppliers_total: 7 });
    });

    it('loads and displays the supplier total', async () => {
        render(
            <ToastProvider>
                <DashboardPage currentUserId={1} />
            </ToastProvider>
        );

        await waitFor(() => expect(screen.getByText('7')).toBeInTheDocument());
    });
});
