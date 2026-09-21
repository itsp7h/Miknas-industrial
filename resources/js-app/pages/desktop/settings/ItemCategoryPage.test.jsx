import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import ItemCategoryPage from './ItemCategoryPage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: {
        private: () => ({ listen: () => ({ listen: () => {} }), stopListening: () => {} }),
        channel: () => ({ listen: () => {}, stopListening: () => {} }),
        leave: () => {},
    },
}));

const CATEGORIES = [
    { id: 1, name: 'Chemical Materials', parent_type: 'raw_material', parent_label: 'Raw Materials', path: 'Raw Materials / Chemical Materials', sort_order: 1, items_count: 11 },
    { id: 3, name: 'Bulk', parent_type: 'raw_material', parent_label: 'Raw Materials', path: 'Raw Materials / Bulk', sort_order: 3, items_count: 5 },
];

const META = { parent_types: [
    { value: 'raw_material', label: 'Raw Materials' },
    { value: 'finished_good', label: 'Finished Goods' },
] };

const renderPage = () => render(<ToastProvider><ItemCategoryPage /></ToastProvider>);

describe('settings ItemCategoryPage', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: CATEGORIES, meta: META });
    });

    it('lists each section by its full path and how many items it holds', async () => {
        renderPage();
        expect(await screen.findByText('Raw Materials / Chemical Materials')).toBeInTheDocument();
        expect(screen.getByText('11 items')).toBeInTheDocument();
        expect(screen.getByText('5 items')).toBeInTheDocument();
    });

    it('adds a section and shows it without a reload', async () => {
        vi.spyOn(client, 'apiPost').mockResolvedValue({
            data: { id: 9, name: 'Packaging', parent_type: 'raw_material', path: 'Raw Materials / Packaging', items_count: 0 },
        });
        renderPage();
        await screen.findByText('Raw Materials / Bulk');

        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Packaging' } });
        fireEvent.click(screen.getByText('Add'));

        expect(await screen.findByText('Raw Materials / Packaging')).toBeInTheDocument();
        expect(screen.getByText('Category saved.')).toBeInTheDocument();
    });

    it('loads a section into the form to rename it', async () => {
        renderPage();
        await screen.findByText('Raw Materials / Bulk');
        fireEvent.click(screen.getAllByText('Edit')[1]);

        expect(screen.getByText('Rename Bulk')).toBeInTheDocument();
        expect(screen.getByLabelText('Name')).toHaveValue('Bulk');
    });

    /**
     * The API refuses to empty a section out from under its items; the page has
     * to say so rather than swallow it.
     */
    it('surfaces the refusal when a section still holds items', async () => {
        vi.spyOn(client, 'apiDelete').mockRejectedValue({
            message: '11 item(s) are still in this section. Move them to another section first.',
        });
        renderPage();
        await screen.findByText('Raw Materials / Chemical Materials');

        fireEvent.click(screen.getAllByText('Delete')[0]);
        fireEvent.click(await screen.findByText('Confirm'));

        await waitFor(() => {
            expect(screen.getByText(/still in this section/)).toBeInTheDocument();
        });
        expect(screen.getByText('Raw Materials / Chemical Materials')).toBeInTheDocument();
    });
});
