import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import FormField from './FormField';

describe('FormField', () => {
    it('renders label and value, and calls onChange', () => {
        const onChange = vi.fn();
        render(<FormField label="Supplier name" name="name" value="Acme" onChange={onChange} />);

        expect(screen.getByLabelText('Supplier name')).toHaveValue('Acme');

        fireEvent.change(screen.getByLabelText('Supplier name'), { target: { value: 'Acme Steel' } });

        expect(onChange).toHaveBeenCalledWith('name', 'Acme Steel');
    });

    it('renders an error message when provided', () => {
        render(<FormField label="Supplier name" name="name" value="" onChange={() => {}} error="Name is required." />);

        expect(screen.getByText('Name is required.')).toBeInTheDocument();
    });
});
