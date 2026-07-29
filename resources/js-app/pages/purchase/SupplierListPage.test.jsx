import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { ToastProvider } from '../../components/ui/Toast';
import * as client from '../../api/client';
import SupplierListPage from './SupplierListPage';

const suppliers = [
    { id: 1, supplier_code: 'SUP-1', name: 'Acme Steel', category: 'Raw Material', email: 'a@acme.test', is_active: true },
    { id: 2, supplier_code: 'SUP-2', name: 'Bolt & Co', category: 'Fasteners', email: 'b@bolt.test', is_active: false },
];

describe('SupplierListPage', () => {
    beforeEach(() => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: suppliers });
    });

    it('loads and lists suppliers, and filters instantly by search', async () => {
        render(
            <ToastProvider>
                <SupplierListPage />
            </ToastProvider>
        );

        await waitFor(() => expect(screen.getByText('Acme Steel')).toBeInTheDocument());
        expect(screen.getByText('Bolt & Co')).toBeInTheDocument();

        fireEvent.change(screen.getByPlaceholderText('Search suppliers…'), { target: { value: 'bolt' } });

        expect(screen.queryByText('Acme Steel')).not.toBeInTheDocument();
        expect(screen.getByText('Bolt & Co')).toBeInTheDocument();
    });

    it('opens the create-supplier modal', async () => {
        render(
            <ToastProvider>
                <SupplierListPage />
            </ToastProvider>
        );

        await waitFor(() => expect(screen.getByText('Acme Steel')).toBeInTheDocument());

        fireEvent.click(screen.getByRole('button', { name: 'New Supplier' }));

        expect(screen.getByText('Add Supplier')).toBeInTheDocument();
    });
});
