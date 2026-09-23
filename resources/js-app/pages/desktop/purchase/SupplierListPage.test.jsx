import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { ToastProvider } from '../../../components/ui/Toast';
import SupplierListPage from './SupplierListPage';
import { AccessProvider } from '../../../layouts/AccessContext';
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

        fireEvent.click(screen.getByText('Add Supplier'));
        fireEvent.change(screen.getByLabelText(/Supplier Name/), { target: { value: 'New Supplier' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save Supplier' }));

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

        expect(screen.getByText('Template').closest('a')).toHaveAttribute('href', '/api/v1/purchase/suppliers/template');
        expect(screen.getByText('Export PDF').closest('a')).toHaveAttribute('href', '/api/v1/purchase/suppliers/export-pdf');
    });

    it('imports a file and shows a summary toast', async () => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: [] });
        const apiPostSpy = vi.spyOn(client, 'apiPostForm').mockResolvedValue({ imported: 2, updated: 1, skipped: 0 });

        render(<ToastProvider><SupplierListPage /></ToastProvider>);
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

        render(<ToastProvider><SupplierListPage /></ToastProvider>);
        await waitFor(() => expect(apiGetSpy).toHaveBeenCalledTimes(1));

        const file = new File(['dummy'], 'suppliers.xlsx', { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        fireEvent.change(screen.getByLabelText('Import Excel'), { target: { files: [file] } });

        await waitFor(() => expect(apiGetSpy).toHaveBeenCalledTimes(2));
        await waitFor(() => expect(screen.getByText('Imported Supplier')).toBeInTheDocument());
    });
});

describe('SupplierListPage — Delete All', () => {
    // The file's other block spies on the same client functions; without
    // this, a call it made counts as one of ours.
    beforeEach(() => {
        vi.restoreAllMocks();
    });

    const SUPPLIERS = [
        { id: 1, name: 'Acme Steel', category: 'Raw Material', is_active: true },
        { id: 2, name: 'Gulf Metals', category: 'Raw Material', is_active: true },
    ];

    /**
     * The button is offered to everyone and disabled for those without the
     * square, rather than hidden: a greyed button with a reason tells someone
     * the capability exists and who to ask.
     */
    const renderWith = (permissions, data = SUPPLIERS) => {
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data });

        return render(
            <ToastProvider>
                <AccessProvider permissions={permissions}>
                    <SupplierListPage />
                </AccessProvider>
            </ToastProvider>
        );
    };

    const deleteAll = () => screen.getByRole('button', { name: /Delete All/ });

    it('disables the button without the square, and says why', async () => {
        renderWith(['suppliers.view']);
        await screen.findByText('Acme Steel');

        expect(deleteAll()).toBeDisabled();
        expect(deleteAll()).toHaveAttribute(
            'title',
            'You do not have permission to delete every supplier. Ask an Admin.'
        );
    });

    it('enables it for someone holding the square', async () => {
        renderWith(['suppliers.view', 'suppliers.delete-all']);
        await screen.findByText('Acme Steel');

        expect(deleteAll()).toBeEnabled();
    });

    /** Holding the square is no use when the list is already empty. */
    it('stays disabled when there is nothing to delete', async () => {
        renderWith(['suppliers.delete-all'], []);
        await waitFor(() => expect(deleteAll()).toBeDisabled());
        expect(deleteAll()).toHaveAttribute('title', 'There are no suppliers to delete.');
    });

    it('will not fire until the words are typed exactly', async () => {
        const del = vi.spyOn(client, 'apiDelete');
        renderWith(['suppliers.view', 'suppliers.delete-all']);
        await screen.findByText('Acme Steel');

        fireEvent.click(deleteAll());
        expect(await screen.findByText('Delete every supplier?')).toBeInTheDocument();

        const confirm = screen.getByRole('button', { name: 'Confirm' });
        expect(confirm).toBeDisabled();

        fireEvent.change(screen.getByLabelText(/Type/), { target: { value: 'delete' } });
        expect(confirm).toBeDisabled();

        fireEvent.change(screen.getByLabelText(/Type/), { target: { value: 'DELETE ALL' } });
        expect(confirm).toBeEnabled();
        expect(del).not.toHaveBeenCalled();
    });

    it('deletes and reports the server’s own count', async () => {
        const del = vi.spyOn(client, 'apiDelete')
            .mockResolvedValue({ deleted: 2, kept: 0, message: '2 supplier(s) deleted.' });
        renderWith(['suppliers.view', 'suppliers.delete-all']);
        await screen.findByText('Acme Steel');

        fireEvent.click(deleteAll());
        fireEvent.change(await screen.findByLabelText(/Type/), { target: { value: 'DELETE ALL' } });
        fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));

        await waitFor(() => expect(del).toHaveBeenCalledWith('/purchase/suppliers'));
        expect(await screen.findByText('2 supplier(s) deleted.')).toBeInTheDocument();
    });

    /** What was kept is the server's to say, so its wording is what shows. */
    it('shows the refusal when nothing could be deleted', async () => {
        vi.spyOn(client, 'apiDelete').mockResolvedValue({
            deleted: 0, kept: 2,
            message: 'Nothing was deleted: every supplier has purchase orders, invoices or other records.',
        });
        renderWith(['suppliers.view', 'suppliers.delete-all']);
        await screen.findByText('Acme Steel');

        fireEvent.click(deleteAll());
        fireEvent.change(await screen.findByLabelText(/Type/), { target: { value: 'DELETE ALL' } });
        fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));

        expect(await screen.findByText(/Nothing was deleted/)).toBeInTheDocument();
    });
});
