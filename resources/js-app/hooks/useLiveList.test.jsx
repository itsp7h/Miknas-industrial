import { describe, it, expect, vi } from 'vitest';
import { renderHook, waitFor, act, screen } from '@testing-library/react';
import { ToastProvider } from '../components/ui/Toast';
import useLiveList from './useLiveList';
import * as client from '../api/client';

let capturedHandler;
let stopListeningSpy;
vi.mock('../echo', () => ({
    echo: {
        private: () => {
            stopListeningSpy = vi.fn();
            return {
                listen: (event, handler) => { capturedHandler = handler; },
                stopListening: stopListeningSpy,
            };
        },
    },
}));

function wrapper({ children }) {
    return <ToastProvider>{children}</ToastProvider>;
}

describe('useLiveList', () => {
    it('fetches the endpoint on mount and exposes the items', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [{ id: 1, name: 'Acme' }] });

        const { result } = renderHook(
            () => useLiveList({ endpoint: '/purchase/suppliers', channel: 'purchase', event: '.supplier.saved' }),
            { wrapper }
        );

        await waitFor(() => expect(result.current.items).toEqual([{ id: 1, name: 'Acme' }]));
    });

    it('shows an error toast when the fetch fails', async () => {
        vi.spyOn(client, 'apiGet').mockRejectedValue(new Error('network error'));

        renderHook(
            () => useLiveList({ endpoint: '/purchase/suppliers', channel: 'purchase', event: '.supplier.saved', errorMessage: 'Failed to load suppliers.' }),
            { wrapper }
        );

        await waitFor(() => expect(screen.getByText('Failed to load suppliers.')).toBeInTheDocument());
    });

    it('merges an incoming broadcast payload into the items by mergeKey, upserting by id', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [{ id: 1, name: 'Acme' }] });

        const { result } = renderHook(
            () => useLiveList({ endpoint: '/purchase/suppliers', channel: 'purchase', event: '.supplier.saved' }),
            { wrapper }
        );

        await waitFor(() => expect(result.current.items).toHaveLength(1));

        act(() => {
            capturedHandler({ id: 1, name: 'Acme Renamed' });
        });
        expect(result.current.items).toEqual([{ id: 1, name: 'Acme Renamed' }]);

        act(() => {
            capturedHandler({ id: 2, name: 'New Supplier' });
        });
        expect(result.current.items).toHaveLength(2);
    });

    it('upsertItem adds/updates an item directly, e.g. after a local form save', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });

        const { result } = renderHook(
            () => useLiveList({ endpoint: '/purchase/suppliers', channel: 'purchase', event: '.supplier.saved' }),
            { wrapper }
        );

        await waitFor(() => expect(client.apiGet).toHaveBeenCalled());

        act(() => {
            result.current.upsertItem({ id: 9, name: 'New Supplier' });
        });
        expect(result.current.items).toEqual([{ id: 9, name: 'New Supplier' }]);
    });

    it('stops listening on the specific event (not the whole channel) on unmount', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });

        const { unmount } = renderHook(
            () => useLiveList({ endpoint: '/purchase/suppliers', channel: 'purchase', event: '.supplier.saved' }),
            { wrapper }
        );

        await waitFor(() => expect(client.apiGet).toHaveBeenCalled());
        unmount();
        expect(stopListeningSpy).toHaveBeenCalledWith('.supplier.saved');
    });

    it('removes an item from the list when a delete broadcast fires for it', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 1, name: 'Acme' }, { id: 2, name: 'Beta' }],
        });

        const { result, unmount } = renderHook(
            () => useLiveList({
                endpoint: '/purchase/suppliers',
                channel: 'purchase',
                event: '.supplier.saved',
                deleteEvent: '.supplier.deleted',
            }),
            { wrapper }
        );

        await waitFor(() => expect(result.current.items).toHaveLength(2));

        // The delete-event effect registers after the save-event effect, so the
        // shared capture vars now hold the delete listener/stopListening spy.
        act(() => {
            capturedHandler({ id: 1 });
        });
        expect(result.current.items).toEqual([{ id: 2, name: 'Beta' }]);

        unmount();
        expect(stopListeningSpy).toHaveBeenCalledWith('.supplier.deleted');
    });
});
