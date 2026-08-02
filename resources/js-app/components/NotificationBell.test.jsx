import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import NotificationBell from './NotificationBell';
import * as client from '../api/client';

let capturedHandler;
vi.mock('../echo', () => ({
    echo: {
        private: () => ({
            listen: (event, handler) => { capturedHandler = handler; },
        }),
        leave: () => {},
    },
}));

describe('NotificationBell', () => {
    it('shows the unread count fetched on mount', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ notifications: [{ id: 1, title: 'Hello', body: 'World', url: null }] });

        render(<NotificationBell currentUserId={1} />);

        await waitFor(() => expect(screen.getByText('1')).toBeInTheDocument());
    });

    it('adds a live-pushed notification without refetching', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ notifications: [] });

        render(<NotificationBell currentUserId={1} />);
        await waitFor(() => expect(client.apiGet).toHaveBeenCalled());

        fireEvent.click(screen.getByLabelText('Notifications'));
        capturedHandler({ id: 2, title: 'New GRN', body: 'GRN #5 confirmed', url: '/app/purchase/grns/5' });

        await waitFor(() => expect(screen.getByText('New GRN')).toBeInTheDocument());
    });
});
