import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import ConfirmModal from './ConfirmModal';

describe('ConfirmModal', () => {
    it('calls onConfirm then onCancel is not called', () => {
        const onConfirm = vi.fn();
        const onCancel = vi.fn();
        render(
            <ConfirmModal
                open
                title="Delete supplier?"
                body="This cannot be undone."
                onConfirm={onConfirm}
                onCancel={onCancel}
            />
        );

        fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));

        expect(onConfirm).toHaveBeenCalledOnce();
        expect(onCancel).not.toHaveBeenCalled();
    });

    it('calls onCancel when cancel is clicked', () => {
        const onCancel = vi.fn();
        render(
            <ConfirmModal
                open
                title="Delete supplier?"
                body="This cannot be undone."
                onConfirm={() => {}}
                onCancel={onCancel}
            />
        );

        fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

        expect(onCancel).toHaveBeenCalledOnce();
    });
});
