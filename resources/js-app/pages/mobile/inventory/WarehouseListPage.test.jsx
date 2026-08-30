import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import WarehouseListPage from './WarehouseListPage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: {
        private: () => ({ listen: () => ({ listen: () => {} }), stopListening: () => {} }),
        channel: () => ({ listen: () => {}, stopListening: () => {} }),
        leave: () => {},
    },
}));

const WAREHOUSES = [
    { id: 1, code: 'WH-MAIN', name: 'Main Store', location: 'Sitra', is_active: true },
    { id: 2, code: 'WH-YARD', name: 'Yard', location: 'Askar', is_active: false },
];

const renderPage = () => render(<ToastProvider><WarehouseListPage /></ToastProvider>);

describe('mobile WarehouseListPage', () => {
    beforeEach(() => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: WAREHOUSES });
    });

    it('renders warehouses as cards, not a table', async () => {
        const { container } = renderPage();
        expect(await screen.findByText('Main Store')).toBeInTheDocument();
        expect(container.querySelector('table')).toBeNull();
    });

    it('filters client-side with a live count', async () => {
        renderPage();
        await screen.findByText('Main Store');
        expect(screen.getByText('2 warehouses')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Search warehouses'), { target: { value: 'yard' } });

        expect(screen.getByText('1 of 2 warehouses')).toBeInTheDocument();
        expect(screen.queryByText('Main Store')).not.toBeInTheDocument();
    });

    it('searches on code and location too', async () => {
        renderPage();
        await screen.findByText('Main Store');
        fireEvent.change(screen.getByLabelText('Search warehouses'), { target: { value: 'askar' } });
        expect(screen.getByText('Yard')).toBeInTheDocument();
        expect(screen.queryByText('Main Store')).not.toBeInTheDocument();
    });

    it('shows a no-results message', async () => {
        renderPage();
        await screen.findByText('Main Store');
        fireEvent.change(screen.getByLabelText('Search warehouses'), { target: { value: 'zzz' } });
        expect(screen.getByText('No warehouses match that search.')).toBeInTheDocument();
    });
});
