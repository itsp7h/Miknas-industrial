import { describe, it, expect, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { ToastProvider } from '../../../components/ui/Toast';
import SupplierListPage from './SupplierListPage';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => {} }), leave: () => {} },
}));

describe('SupplierListPage (mobile)', () => {
    it('renders suppliers as cards, not a table', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [{ id: 1, name: 'Acme Steel', category: 'Raw Material', is_active: true }] });

        render(<ToastProvider><SupplierListPage /></ToastProvider>);

        await waitFor(() => expect(screen.getByText('Acme Steel')).toBeInTheDocument());
        expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });
});
