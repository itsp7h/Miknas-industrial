import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import SupplierForm from './SupplierForm';

describe('SupplierForm', () => {
    it('submits the current field values', () => {
        const onSubmit = vi.fn();
        render(<SupplierForm initialValues={{ name: '', email: '' }} errors={{}} onSubmit={onSubmit} submitting={false} />);

        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Acme Steel' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(onSubmit).toHaveBeenCalledWith(expect.objectContaining({ name: 'Acme Steel' }));
    });

    it('shows validation errors passed in as props', () => {
        render(<SupplierForm initialValues={{ name: '' }} errors={{ name: 'Name is required.' }} onSubmit={() => {}} submitting={false} />);

        expect(screen.getByText('Name is required.')).toBeInTheDocument();
    });
});
