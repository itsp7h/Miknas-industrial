import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import Modal from './Modal';

describe('Modal', () => {
    it('renders children and title when open', () => {
        render(<Modal open title="Edit Supplier" onClose={() => {}}>Body content</Modal>);

        expect(screen.getByText('Edit Supplier')).toBeInTheDocument();
        expect(screen.getByText('Body content')).toBeInTheDocument();
    });

    it('renders nothing when closed', () => {
        render(<Modal open={false} title="Edit Supplier" onClose={() => {}}>Body content</Modal>);

        expect(screen.queryByText('Body content')).not.toBeInTheDocument();
    });

    it('calls onClose when the close button is clicked', () => {
        const onClose = vi.fn();
        render(<Modal open title="Edit Supplier" onClose={onClose}>Body content</Modal>);

        fireEvent.click(screen.getByRole('button', { name: 'Close' }));

        expect(onClose).toHaveBeenCalledOnce();
    });
});
