import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
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

describe('mobile ItemListPage', () => {
    beforeEach(() => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: ITEMS });
    });

    it('renders items as cards rather than a table', async () => {
        const { container } = renderPage();
        expect(await screen.findByText('Steel Rod')).toBeInTheDocument();
        expect(container.querySelector('table')).toBeNull();
    });

    // CLAUDE.md gotcha #6: search filters client-side over the whole list,
    // with a live count and a no-results message. No refetch, no URL params.
    it('filters client-side and reports a live count', async () => {
        renderPage();
        await screen.findByText('Steel Rod');
        expect(screen.getByText('2 items')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Search items'), { target: { value: 'widget' } });

        expect(screen.getByText('1 of 2 items')).toBeInTheDocument();
        expect(screen.queryByText('Steel Rod')).not.toBeInTheDocument();
        expect(screen.getByText('Widget')).toBeInTheDocument();
    });

    it('searches on item code too, not just name', async () => {
        renderPage();
        await screen.findByText('Steel Rod');
        fireEvent.change(screen.getByLabelText('Search items'), { target: { value: 'ITEM-00002' } });
        expect(screen.getByText('Widget')).toBeInTheDocument();
        expect(screen.queryByText('Steel Rod')).not.toBeInTheDocument();
    });

    it('shows a no-results message instead of an empty list', async () => {
        renderPage();
        await screen.findByText('Steel Rod');
        fireEvent.change(screen.getByLabelText('Search items'), { target: { value: 'zzzz' } });
        expect(screen.getByText('No items match that search.')).toBeInTheDocument();
    });

    it('does not refetch while searching', async () => {
        renderPage();
        await screen.findByText('Steel Rod');
        const callsBefore = client.apiGet.mock.calls.length;
        fireEvent.change(screen.getByLabelText('Search items'), { target: { value: 'steel' } });
        expect(client.apiGet.mock.calls.length).toBe(callsBefore);
    });

    it('puts import and export behind an Actions sheet to keep the header clean', async () => {
        renderPage();
        await screen.findByText('Steel Rod');
        expect(screen.queryByText('Download Template')).not.toBeInTheDocument();
        fireEvent.click(screen.getByText('Actions'));
        expect(await screen.findByText('Download Template')).toBeInTheDocument();
        expect(screen.getByText('Export PDF')).toBeInTheDocument();
    });
});
