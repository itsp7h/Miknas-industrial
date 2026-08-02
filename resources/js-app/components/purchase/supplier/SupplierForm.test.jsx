import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import SupplierForm from './SupplierForm';
import * as client from '../../../api/client';

describe('SupplierForm', () => {
    it('requires a name before submitting', async () => {
        const onSaved = vi.fn();
        render(<SupplierForm supplier={null} onSaved={onSaved} onCancel={() => {}} />);

        fireEvent.click(screen.getByText('Save'));

        await waitFor(() => expect(screen.getByText(/name is required/i)).toBeInTheDocument());
        expect(onSaved).not.toHaveBeenCalled();
    });

    it('posts a new supplier and calls onSaved', async () => {
        const created = { id: 5, name: 'Acme Steel' };
        vi.spyOn(client, 'apiPost').mockResolvedValue({ data: created });
        const onSaved = vi.fn();

        render(<SupplierForm supplier={null} onSaved={onSaved} onCancel={() => {}} />);
        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Acme Steel' } });
        fireEvent.click(screen.getByText('Save'));

        await waitFor(() => expect(onSaved).toHaveBeenCalledWith(created));
        expect(client.apiPost).toHaveBeenCalledWith('/purchase/suppliers', expect.objectContaining({ name: 'Acme Steel' }));
    });

    it('puts an edit for an existing supplier', async () => {
        const updated = { id: 5, name: 'Acme Renamed' };
        vi.spyOn(client, 'apiPut').mockResolvedValue({ data: updated });
        const onSaved = vi.fn();

        render(<SupplierForm supplier={{ id: 5, name: 'Acme Steel' }} onSaved={onSaved} onCancel={() => {}} />);
        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Acme Renamed' } });
        fireEvent.click(screen.getByText('Save'));

        await waitFor(() => expect(onSaved).toHaveBeenCalledWith(updated));
        expect(client.apiPut).toHaveBeenCalledWith('/purchase/suppliers/5', expect.objectContaining({ name: 'Acme Renamed' }));
    });
});
