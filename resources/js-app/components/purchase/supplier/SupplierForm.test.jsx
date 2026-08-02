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

    it('renders a Tax Number field and an Active checkbox, and submits both', async () => {
        const created = { id: 5, name: 'Acme Steel', tax_number: 'TRN-1', is_active: false };
        vi.spyOn(client, 'apiPost').mockResolvedValue({ data: created });
        const onSaved = vi.fn();

        render(<SupplierForm supplier={null} onSaved={onSaved} onCancel={() => {}} />);
        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Acme Steel' } });
        fireEvent.change(screen.getByLabelText('Tax Number'), { target: { value: 'TRN-1' } });
        fireEvent.click(screen.getByLabelText('Active'));
        fireEvent.click(screen.getByText('Save'));

        await waitFor(() => expect(onSaved).toHaveBeenCalledWith(created));
        expect(client.apiPost).toHaveBeenCalledWith(
            '/purchase/suppliers',
            expect.objectContaining({ tax_number: 'TRN-1', is_active: false })
        );
    });

    it('renders the secondary contact/business fields and submits them', async () => {
        const created = {
            id: 5,
            name: 'Acme Steel',
            secondary_email: 'sales@acme.test',
            phone2: '555-0200',
            whatsapp: '555-0300',
            website: 'https://acme.test',
            credit_terms: 'Net 30',
            remarks: 'Preferred vendor',
        };
        vi.spyOn(client, 'apiPost').mockResolvedValue({ data: created });
        const onSaved = vi.fn();

        render(<SupplierForm supplier={null} onSaved={onSaved} onCancel={() => {}} />);
        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Acme Steel' } });
        fireEvent.change(screen.getByLabelText('Secondary Email'), { target: { value: 'sales@acme.test' } });
        fireEvent.change(screen.getByLabelText('Phone 2'), { target: { value: '555-0200' } });
        fireEvent.change(screen.getByLabelText('WhatsApp'), { target: { value: '555-0300' } });
        fireEvent.change(screen.getByLabelText('Website'), { target: { value: 'https://acme.test' } });
        fireEvent.change(screen.getByLabelText('Credit Terms'), { target: { value: 'Net 30' } });
        fireEvent.change(screen.getByLabelText('Remarks'), { target: { value: 'Preferred vendor' } });
        fireEvent.click(screen.getByText('Save'));

        await waitFor(() => expect(onSaved).toHaveBeenCalledWith(created));
        expect(client.apiPost).toHaveBeenCalledWith(
            '/purchase/suppliers',
            expect.objectContaining({
                secondary_email: 'sales@acme.test',
                phone2: '555-0200',
                whatsapp: '555-0300',
                website: 'https://acme.test',
                credit_terms: 'Net 30',
                remarks: 'Preferred vendor',
            })
        );
    });

    it('populates every field (not blank) when opening a row that arrived via a full-payload broadcast', () => {
        // Simulates a supplier delivered by the .supplier.saved live event now that
        // SupplierSaved::broadcastWith() returns the full SupplierResource field set —
        // editing it must not blank out fields, unlike the old partial payload.
        const broadcastSupplier = {
            id: 7,
            supplier_code: 'SUP-7',
            name: 'Acme Steel',
            category: 'Raw Material',
            contact_person: 'Jane Doe',
            email: 'jane@example.com',
            phone: '555-0100',
            whatsapp_number: '555-0101',
            address: '123 Main St',
            tax_number: 'TRN-999',
            credit_days: 45,
            is_active: true,
        };

        render(<SupplierForm supplier={broadcastSupplier} onSaved={() => {}} onCancel={() => {}} />);

        expect(screen.getByLabelText('Name')).toHaveValue('Acme Steel');
        expect(screen.getByLabelText('Contact Person')).toHaveValue('Jane Doe');
        expect(screen.getByLabelText('Email')).toHaveValue('jane@example.com');
        expect(screen.getByLabelText('Phone')).toHaveValue('555-0100');
        expect(screen.getByLabelText('Address')).toHaveValue('123 Main St');
        expect(screen.getByLabelText('Tax Number')).toHaveValue('TRN-999');
        expect(screen.getByLabelText('Credit Days')).toHaveValue(45);
        expect(screen.getByLabelText('Active')).toBeChecked();
    });

    it('coerces null field values to empty strings instead of leaving inputs uncontrolled', () => {
        render(
            <SupplierForm
                supplier={{ id: 3, name: 'Acme', email: null, phone: undefined, is_active: null }}
                onSaved={() => {}}
                onCancel={() => {}}
            />
        );

        expect(screen.getByLabelText('Email')).toHaveValue('');
        expect(screen.getByLabelText('Phone')).toHaveValue('');
        expect(screen.getByLabelText('Active')).toBeChecked();
    });
});
