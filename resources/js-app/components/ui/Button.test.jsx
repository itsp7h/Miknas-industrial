import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import Button from './Button';

describe('Button', () => {
    it('calls onClick when clicked', () => {
        const onClick = vi.fn();
        render(<Button onClick={onClick}>Save</Button>);

        fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(onClick).toHaveBeenCalledOnce();
    });

    it('does not call onClick when disabled', () => {
        const onClick = vi.fn();
        render(<Button onClick={onClick} disabled>Save</Button>);

        fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(onClick).not.toHaveBeenCalled();
    });

    it('shows loading state and disables the button', () => {
        render(<Button loading>Save</Button>);

        expect(screen.getByRole('button')).toBeDisabled();
        expect(screen.getByText('Loading…')).toBeInTheDocument();
    });

    it('renders a link variant without block padding, for use inside table rows', () => {
        const onClick = vi.fn();
        render(<Button variant="link" onClick={onClick}>Edit</Button>);

        const button = screen.getByRole('button', { name: 'Edit' });
        fireEvent.click(button);

        expect(onClick).toHaveBeenCalledOnce();
        expect(button.className).not.toMatch(/px-4/);
    });
});
