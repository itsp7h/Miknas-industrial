import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { ToastProvider } from '../../../components/ui/Toast';
import SupplierListPage from './SupplierListPage';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => {}, stopListening: () => {} }) },
}));

describe('SupplierListPage', () => {
    it('loads and displays suppliers', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [{ id: 1, name: 'Acme Steel', category: 'Raw Material', is_active: true }] });

        render(<ToastProvider><SupplierListPage /></ToastProvider>);

        await waitFor(() => expect(screen.getByText('Acme Steel')).toBeInTheDocument());
    });

    it('opens the create modal and adds the new supplier to the list on save', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });
        vi.spyOn(client, 'apiPost').mockResolvedValue({ data: { id: 9, name: 'New Supplier', category: null, is_active: true } });

        render(<ToastProvider><SupplierListPage /></ToastProvider>);
        await waitFor(() => expect(client.apiGet).toHaveBeenCalled());

        fireEvent.click(screen.getByText('New Supplier'));
        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'New Supplier' } });
        fireEvent.click(screen.getByText('Save'));

        await waitFor(() => expect(screen.getByRole('cell', { name: 'New Supplier' })).toBeInTheDocument());
    });

    it('shows an error toast when the suppliers fail to load', async () => {
        vi.spyOn(client, 'apiGet').mockRejectedValue(new Error('network error'));

        render(<ToastProvider><SupplierListPage /></ToastProvider>);

        await waitFor(() => expect(screen.getByText('Failed to load suppliers.')).toBeInTheDocument());
    });
});
