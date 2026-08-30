import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import ItemListPage from './ItemListPage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: {
        private: () => ({ listen: () => ({ listen: () => {} }), stopListening: () => {} }),
        channel: () => ({ listen: () => {}, stopListening: () => {} }),
        leave: () => {},
    },
}));

const ITEMS = [
    { id: 1, item_code: 'ITEM-00001', item_name: 'Steel Rod', category: 'raw_material', unit_of_measure: 'PCS', minimum_stock_level: '5', cost_price: '10.00', is_active: true },
    { id: 2, item_code: 'ITEM-00002', item_name: 'Widget', category: 'finished_good', unit_of_measure: 'BOX', minimum_stock_level: '2', cost_price: '99.00', is_active: false },
];

function renderPage() {
    return render(<ToastProvider><ItemListPage /></ToastProvider>);
}

describe('desktop ItemListPage', () => {
    beforeEach(() => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: ITEMS });
    });

    it('lists items from the API', async () => {
        renderPage();
        expect(await screen.findByText('Steel Rod')).toBeInTheDocument();
        expect(screen.getByText('ITEM-00002')).toBeInTheDocument();
    });

    it('renders the category enum as a human label rather than the raw value', async () => {
        renderPage();
        expect(await screen.findByText('Raw Material')).toBeInTheDocument();
        expect(screen.queryByText('raw_material')).not.toBeInTheDocument();
    });

    it('shows active state per row', async () => {
        renderPage();
        await screen.findByText('Steel Rod');
        expect(screen.getByText('Yes')).toBeInTheDocument();
        expect(screen.getByText('No')).toBeInTheDocument();
    });

    it('opens the create modal from the New Item button', async () => {
        renderPage();
        await screen.findByText('Steel Rod');
        fireEvent.click(screen.getByText('New Item'));
        expect(await screen.findByText('New Item', { selector: 'h2, h3, div' })).toBeTruthy();
        expect(screen.getByLabelText('Item Name')).toBeInTheDocument();
    });

    it('asks for confirmation before deleting', async () => {
        renderPage();
        await screen.findByText('Steel Rod');
        fireEvent.click(screen.getAllByText('Delete')[0]);
        expect(await screen.findByText(/permanently remove "Steel Rod"/)).toBeInTheDocument();
    });

    it('keeps the row and explains when the API deactivates instead of deleting', async () => {
        vi.spyOn(client, 'apiDelete').mockResolvedValue({
            deactivated: true,
            message: 'Item has stock movements, so it was deactivated rather than deleted.',
        });
        renderPage();
        await screen.findByText('Steel Rod');
        fireEvent.click(screen.getAllByText('Delete')[0]);
        fireEvent.click(await screen.findByText('Confirm'));
        await waitFor(() => {
            expect(screen.getByText(/deactivated rather than deleted/)).toBeInTheDocument();
        });
    });
});
