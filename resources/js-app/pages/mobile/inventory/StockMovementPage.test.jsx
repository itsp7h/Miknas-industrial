import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import StockMovementPage from './StockMovementPage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: {
        private: () => ({ listen: () => ({ listen: () => {} }), stopListening: () => {} }),
        channel: () => ({ listen: () => {}, stopListening: () => {} }),
        leave: () => {},
    },
}));

const MOVEMENTS = [
    { id: 1, item_name: 'Steel Rod', warehouse_name: 'Main', type: 'in', quantity: '10', notes: 'PO receipt', created_at: '2026-08-01T10:00:00+00:00' },
    { id: 2, item_name: 'Widget', warehouse_name: 'Yard', type: 'out', quantity: '3', notes: null, created_at: '2026-08-02T10:00:00+00:00' },
];

const renderPage = () => render(<ToastProvider><StockMovementPage /></ToastProvider>);

describe('mobile StockMovementPage', () => {
    beforeEach(() => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: MOVEMENTS });
    });

    it('renders movements as cards', async () => {
        const { container } = renderPage();
        expect(await screen.findByText('Steel Rod')).toBeInTheDocument();
        expect(container.querySelector('table')).toBeNull();
    });

    it('labels movement types in plain language rather than raw enum values', async () => {
        renderPage();
        await screen.findByText('Steel Rod');
        expect(screen.getByText(/Stock In/)).toBeInTheDocument();
        expect(screen.getByText(/Stock Out/)).toBeInTheDocument();
    });

    it('filters client-side with a live count', async () => {
        renderPage();
        await screen.findByText('Steel Rod');
        expect(screen.getByText('2 movements')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Search movements'), { target: { value: 'widget' } });

        expect(screen.getByText('1 of 2 movements')).toBeInTheDocument();
        expect(screen.queryByText('Steel Rod')).not.toBeInTheDocument();
    });

    it('shows a no-results message', async () => {
        renderPage();
        await screen.findByText('Steel Rod');
        fireEvent.change(screen.getByLabelText('Search movements'), { target: { value: 'zzz' } });
        expect(screen.getByText('No movements match that search.')).toBeInTheDocument();
    });
});
