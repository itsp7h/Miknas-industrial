import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { ToastProvider } from '../../../components/ui/Toast';
import { AccessProvider } from '../../../layouts/AccessContext';
import SupplierListPage from './SupplierListPage';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => {}, stopListening: () => {} }) },
}));

const Page = () => (
    <ToastProvider>
        <AccessProvider isAdmin>
            <MemoryRouter><SupplierListPage /></MemoryRouter>
        </AccessProvider>
    </ToastProvider>
);

describe('SupplierListPage (mobile)', () => {
    it('filters client-side with a live count and no extra request', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [
                { id: 1, name: 'Acme Steel', category: 'Steel', is_active: true },
                { id: 2, name: 'Zenith Supply', category: 'Bolts', is_active: true },
            ],
        });

        render(<Page />);
        await waitFor(() => expect(screen.getByText('Acme Steel')).toBeInTheDocument());

        const before = client.apiGet.mock.calls.length;
        fireEvent.change(screen.getByLabelText('Search suppliers'), { target: { value: 'zenith' } });

        expect(screen.getByText('1 of 2 suppliers')).toBeInTheDocument();
        expect(screen.queryByText('Acme Steel')).not.toBeInTheDocument();
        expect(client.apiGet.mock.calls.length).toBe(before);
    });

    it('renders suppliers as cards, not a table', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [{ id: 1, name: 'Acme Steel', category: 'Raw Material', is_active: true }] });

        render(<Page />);

        await waitFor(() => expect(screen.getByText('Acme Steel')).toBeInTheDocument());
        expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });

    it('shows an error toast when the suppliers fail to load', async () => {
        vi.spyOn(client, 'apiGet').mockRejectedValue(new Error('network error'));

        render(<Page />);

        await waitFor(() => expect(screen.getByText('Failed to load suppliers.')).toBeInTheDocument());
    });

    it('deletes a supplier after confirming', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [{ id: 1, name: 'Acme Steel', category: null, is_active: true }] });
        vi.spyOn(client, 'apiDelete').mockResolvedValue({});

        render(<Page />);
        await waitFor(() => expect(screen.getByText('Acme Steel')).toBeInTheDocument());

        // A row opens the supplier's sheet; delete is in it.
        fireEvent.click(screen.getByText('Acme Steel'));
        fireEvent.click(screen.getByRole('button', { name: 'Delete supplier' }));
        fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));

        await waitFor(() => expect(client.apiDelete).toHaveBeenCalledWith('/purchase/suppliers/1'));
        await waitFor(() => expect(screen.queryByText('Acme Steel')).not.toBeInTheDocument());
    });

    it('offers template and PDF export as plain download links from the tools sheet', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });

        render(<Page />);

        fireEvent.click(screen.getByRole('button', { name: 'Import or export' }));
        expect(screen.getByText('Download import template').closest('a')).toHaveAttribute('href', '/api/v1/purchase/suppliers/template');
        expect(screen.getByText('Export as PDF').closest('a')).toHaveAttribute('href', '/api/v1/purchase/suppliers/export-pdf');
    });

    it('imports a file and shows a summary toast', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });
        const apiPostSpy = vi.spyOn(client, 'apiPostForm').mockResolvedValue({ imported: 2, updated: 1, skipped: 0 });

        render(<Page />);
        const file = new File(['dummy'], 'suppliers.xlsx', { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        fireEvent.change(screen.getByLabelText('Import Excel'), { target: { files: [file] } });

        await waitFor(() => expect(apiPostSpy).toHaveBeenCalled());
        await waitFor(() => expect(screen.getByText(/2 added, 1 updated/i)).toBeInTheDocument());
    });

    it('refetches the supplier list after a successful import', async () => {
        const apiGetSpy = vi.spyOn(client, 'apiGet')
            .mockResolvedValueOnce({ data: [] })
            .mockResolvedValueOnce({ data: [{ id: 9, name: 'Imported Supplier', category: null, is_active: true }] });
        vi.spyOn(client, 'apiPostForm').mockResolvedValue({ imported: 1, updated: 0, skipped: 0 });
        apiGetSpy.mockClear();

        render(<Page />);
        await waitFor(() => expect(apiGetSpy).toHaveBeenCalledTimes(1));

        const file = new File(['dummy'], 'suppliers.xlsx', { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        fireEvent.change(screen.getByLabelText('Import Excel'), { target: { files: [file] } });

        await waitFor(() => expect(apiGetSpy).toHaveBeenCalledTimes(2));
        await waitFor(() => expect(screen.getByText('Imported Supplier')).toBeInTheDocument());
    });
});
