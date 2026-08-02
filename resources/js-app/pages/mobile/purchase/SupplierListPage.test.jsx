import { describe, it, expect, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { ToastProvider } from '../../../components/ui/Toast';
import SupplierListPage from './SupplierListPage';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => {}, stopListening: () => {} }) },
}));

describe('SupplierListPage (mobile)', () => {
    it('renders suppliers as cards, not a table', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [{ id: 1, name: 'Acme Steel', category: 'Raw Material', is_active: true }] });

        render(<ToastProvider><SupplierListPage /></ToastProvider>);

        await waitFor(() => expect(screen.getByText('Acme Steel')).toBeInTheDocument());
        expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });

    it('shows an error toast when the suppliers fail to load', async () => {
        vi.spyOn(client, 'apiGet').mockRejectedValue(new Error('network error'));

        render(<ToastProvider><SupplierListPage /></ToastProvider>);

        await waitFor(() => expect(screen.getByText('Failed to load suppliers.')).toBeInTheDocument());
    });
});
