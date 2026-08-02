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

    it('deletes a supplier after confirming', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [{ id: 1, name: 'Acme Steel', category: null, is_active: true }] });
        vi.spyOn(client, 'apiDelete').mockResolvedValue({});

        render(<ToastProvider><SupplierListPage /></ToastProvider>);
        await waitFor(() => expect(screen.getByText('Acme Steel')).toBeInTheDocument());

        fireEvent.click(screen.getByText('Delete'));
        fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));

        await waitFor(() => expect(client.apiDelete).toHaveBeenCalledWith('/purchase/suppliers/1'));
        await waitFor(() => expect(screen.queryByText('Acme Steel')).not.toBeInTheDocument());
    });

    it('renders template and PDF export as plain download links', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });

        render(<ToastProvider><SupplierListPage /></ToastProvider>);

        expect(screen.getByText('Download Template').closest('a')).toHaveAttribute('href', '/api/v1/purchase/suppliers/template');
        expect(screen.getByText('Export PDF').closest('a')).toHaveAttribute('href', '/api/v1/purchase/suppliers/export-pdf');
    });

    it('imports a file and shows a summary toast', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });
        const apiPostSpy = vi.spyOn(client, 'apiPostForm').mockResolvedValue({ imported: 2, updated: 1, skipped: 0 });

        render(<ToastProvider><SupplierListPage /></ToastProvider>);
        const file = new File(['dummy'], 'suppliers.xlsx', { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        fireEvent.change(screen.getByLabelText('Import'), { target: { files: [file] } });

        await waitFor(() => expect(apiPostSpy).toHaveBeenCalled());
        await waitFor(() => expect(screen.getByText(/2 added, 1 updated/i)).toBeInTheDocument());
    });

    it('refetches the supplier list after a successful import', async () => {
        const apiGetSpy = vi.spyOn(client, 'apiGet')
            .mockResolvedValueOnce({ data: [] })
            .mockResolvedValueOnce({ data: [{ id: 9, name: 'Imported Supplier', category: null, is_active: true }] });
        vi.spyOn(client, 'apiPostForm').mockResolvedValue({ imported: 1, updated: 0, skipped: 0 });
        apiGetSpy.mockClear();

        render(<ToastProvider><SupplierListPage /></ToastProvider>);
        await waitFor(() => expect(apiGetSpy).toHaveBeenCalledTimes(1));

        const file = new File(['dummy'], 'suppliers.xlsx', { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        fireEvent.change(screen.getByLabelText('Import'), { target: { files: [file] } });

        await waitFor(() => expect(apiGetSpy).toHaveBeenCalledTimes(2));
        await waitFor(() => expect(screen.getByText('Imported Supplier')).toBeInTheDocument());
    });
});
