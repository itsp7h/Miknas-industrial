import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import SupplierModal from './SupplierModal';
import * as client from '../../../api/client';

describe('SupplierModal', () => {
    it('requires a name before submitting', async () => {
        const onSaved = vi.fn();
        render(<SupplierModal supplier={null} onSaved={onSaved} onCancel={() => {}} />);

        fireEvent.click(screen.getByRole('button', { name: 'Save Supplier' }));

        await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent(/name is required/i));
        expect(onSaved).not.toHaveBeenCalled();
    });

    it('posts a new supplier and calls onSaved', async () => {
        const created = { id: 5, name: 'Acme Steel' };
        vi.spyOn(client, 'apiPost').mockResolvedValue({ data: created });
        const onSaved = vi.fn();

        render(<SupplierModal supplier={null} onSaved={onSaved} onCancel={() => {}} />);
        fireEvent.change(screen.getByLabelText(/Supplier Name/), { target: { value: 'Acme Steel' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save Supplier' }));

        await waitFor(() => expect(onSaved).toHaveBeenCalledWith(created));
        expect(client.apiPost).toHaveBeenCalledWith('/purchase/suppliers', expect.objectContaining({ name: 'Acme Steel' }));
    });

    it('puts an edit for an existing supplier', async () => {
        const updated = { id: 5, name: 'Acme Renamed' };
        vi.spyOn(client, 'apiPut').mockResolvedValue({ data: updated });
        const onSaved = vi.fn();

        render(<SupplierModal supplier={{ id: 5, name: 'Acme Steel' }} onSaved={onSaved} onCancel={() => {}} />);
        fireEvent.change(screen.getByLabelText(/Supplier Name/), { target: { value: 'Acme Renamed' } });
        // Editing carries EDIT_CHROME, so the action reads "Save Changes" —
        // the same way the MPR modal distinguishes its two modes.
        fireEvent.click(screen.getByRole('button', { name: 'Save Changes' }));

        await waitFor(() => expect(onSaved).toHaveBeenCalledWith(updated));
        expect(client.apiPut).toHaveBeenCalledWith('/purchase/suppliers/5', expect.objectContaining({ name: 'Acme Renamed' }));
    });

    it('renders a Tax Number field and an Active checkbox, and submits both', async () => {
        const created = { id: 5, name: 'Acme Steel', tax_number: 'TRN-1', is_active: false };
        vi.spyOn(client, 'apiPost').mockResolvedValue({ data: created });
        const onSaved = vi.fn();

        render(<SupplierModal supplier={null} onSaved={onSaved} onCancel={() => {}} />);
        fireEvent.change(screen.getByLabelText(/Supplier Name/), { target: { value: 'Acme Steel' } });
        fireEvent.change(screen.getByLabelText('Tax Number'), { target: { value: 'TRN-1' } });
        fireEvent.click(screen.getByLabelText('Active'));
        fireEvent.click(screen.getByRole('button', { name: 'Save Supplier' }));

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
            credit_terms: 'yes',
            remarks: 'Preferred vendor',
        };
        vi.spyOn(client, 'apiPost').mockResolvedValue({ data: created });
        const onSaved = vi.fn();

        render(<SupplierModal supplier={null} onSaved={onSaved} onCancel={() => {}} />);
        fireEvent.change(screen.getByLabelText(/Supplier Name/), { target: { value: 'Acme Steel' } });
        fireEvent.change(screen.getByLabelText('Secondary Email'), { target: { value: 'sales@acme.test' } });
        fireEvent.change(screen.getByLabelText('Phone 2'), { target: { value: '555-0200' } });
        fireEvent.change(screen.getByLabelText(/WhatsApp \(directory\)/), { target: { value: '555-0300' } });
        fireEvent.change(screen.getByLabelText('Website'), { target: { value: 'https://acme.test' } });
        fireEvent.change(screen.getByLabelText('Credit'), { target: { value: 'yes' } });
        fireEvent.change(screen.getByLabelText('Remarks'), { target: { value: 'Preferred vendor' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save Supplier' }));

        await waitFor(() => expect(onSaved).toHaveBeenCalledWith(created));
        expect(client.apiPost).toHaveBeenCalledWith(
            '/purchase/suppliers',
            expect.objectContaining({
                secondary_email: 'sales@acme.test',
                phone2: '555-0200',
                whatsapp: '555-0300',
                website: 'https://acme.test',
                credit_terms: 'yes',
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

        render(<SupplierModal supplier={broadcastSupplier} onSaved={() => {}} onCancel={() => {}} />);

        expect(screen.getByLabelText(/Supplier Name/)).toHaveValue('Acme Steel');
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
            <SupplierModal
                supplier={{ id: 3, name: 'Acme', email: null, phone: undefined, is_active: null }}
                onSaved={() => {}}
                onCancel={() => {}}
            />
        );

        expect(screen.getByLabelText('Email')).toHaveValue('');
        expect(screen.getByLabelText('Phone')).toHaveValue('');
        expect(screen.getByLabelText('Active')).toBeChecked();
    });

    /**
     * The Credit field was a free-text box labelled "Credit (Y/N)", but
     * `hasCredit()` only counts "y"/"yes" — so a plausible entry like
     * "Net 30" saved and then silently failed to badge the supplier.
     */
    it('offers credit as a choice rather than free text', () => {
        render(<SupplierModal supplier={null} onSaved={() => {}} onCancel={() => {}} />);

        const credit = screen.getByLabelText('Credit');
        expect(credit.tagName).toBe('SELECT');
        expect([...credit.options].map((o) => o.value)).toEqual(['', 'yes', 'no']);
    });

    /** The two WhatsApp columns are not primary and secondary — they differ in what reads them. */
    it('distinguishes the WhatsApp number that is notified from the one merely displayed', () => {
        render(<SupplierModal supplier={null} onSaved={() => {}} onCancel={() => {}} />);

        expect(screen.getByLabelText(/WhatsApp \(notifications\)/)).toHaveAttribute('name', 'whatsapp_number');
        expect(screen.getByLabelText(/WhatsApp \(directory\)/)).toHaveAttribute('name', 'whatsapp');
        expect(screen.getByText(/LPO and RFQ messages go here/)).toBeInTheDocument();
    });

    /** It is a real form now, so the keyboard can submit it. */
    it('submits on Enter from within the form', async () => {
        vi.spyOn(client, 'apiPost').mockResolvedValue({ data: { id: 9, name: 'Acme' } });
        const onSaved = vi.fn();
        const { container } = render(<SupplierModal supplier={null} onSaved={onSaved} onCancel={() => {}} />);

        fireEvent.change(screen.getByLabelText(/Supplier Name/), { target: { value: 'Acme' } });
        fireEvent.submit(container.querySelector('form'));

        await waitFor(() => expect(onSaved).toHaveBeenCalled());
    });

    /**
     * A failure with no field errors used to clear the error state and show
     * nothing at all — the dialog just sat there as if Save had not been
     * clicked.
     */
    it('reports a failure that carries no field errors', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({ message: 'Server unavailable.' });
        const onSaved = vi.fn();
        render(<SupplierModal supplier={null} onSaved={onSaved} onCancel={() => {}} />);

        fireEvent.change(screen.getByLabelText(/Supplier Name/), { target: { value: 'Acme' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save Supplier' }));

        expect(await screen.findByRole('alert')).toHaveTextContent('Server unavailable.');
        expect(onSaved).not.toHaveBeenCalled();
        // And it is usable again, not stuck in a saving state.
        expect(screen.getByRole('button', { name: 'Save Supplier' })).toBeEnabled();
    });

    /**
     * A 422 lands twice on purpose: in the summary at the top of the dialog,
     * the way the MPR modal reports one, and against the field itself. The
     * summary is what you see without scrolling a long form; the field is
     * what tells you which box to fix.
     */
    it('shows a server field error in the summary and against its field', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({ errors: { email: ['The email has already been taken.'] } });
        render(<SupplierModal supplier={null} onSaved={() => {}} onCancel={() => {}} />);

        fireEvent.change(screen.getByLabelText(/Supplier Name/), { target: { value: 'Acme' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save Supplier' }));

        expect(await screen.findByRole('alert')).toHaveTextContent('The email has already been taken.');
        expect(screen.getByLabelText('Email')).toHaveAttribute('aria-invalid', 'true');
        expect(screen.getAllByText('The email has already been taken.')).toHaveLength(2);
    });

    /** Editing the offending field clears its message rather than leaving it under the new value. */
    it('clears a field error as soon as that field is edited', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({ errors: { email: ['The email has already been taken.'] } });
        render(<SupplierModal supplier={null} onSaved={() => {}} onCancel={() => {}} />);

        fireEvent.change(screen.getByLabelText(/Supplier Name/), { target: { value: 'Acme' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save Supplier' }));
        await screen.findByRole('alert');

        fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'free@acme.test' } });

        expect(screen.queryByText('The email has already been taken.')).not.toBeInTheDocument();
        expect(screen.getByLabelText('Email')).not.toHaveAttribute('aria-invalid');
    });

    /** The page's toolbar already uses these; the form's buttons had no class at all. */
    it('uses the shared button classes rather than bare browser buttons', () => {
        render(<SupplierModal supplier={null} onSaved={() => {}} onCancel={() => {}} />);

        expect(screen.getByRole('button', { name: 'Save Supplier' })).toHaveClass('btn-primary');
        expect(screen.getByRole('button', { name: 'Cancel' })).toHaveClass('btn-secondary');
    });

    it('marks the only required field as required', () => {
        render(<SupplierModal supplier={null} onSaved={() => {}} onCancel={() => {}} />);

        expect(screen.getByText('Supplier Name').textContent).toContain('*');
    });
});
