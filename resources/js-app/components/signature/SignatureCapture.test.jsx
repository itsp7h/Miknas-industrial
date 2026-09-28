import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import SignatureCapture from './SignatureCapture';

describe('SignatureCapture', () => {
    it('starts on the pad, with nothing to clear yet', () => {
        render(<SignatureCapture onChange={() => {}} />);

        expect(screen.getByRole('tab', { name: /Draw/ })).toHaveAttribute('aria-selected', 'true');
        expect(screen.getByLabelText('Signature pad')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Clear' })).toBeDisabled();
    });

    it('switches to upload and reports nothing until an image is chosen', () => {
        const onChange = vi.fn();
        render(<SignatureCapture onChange={onChange} />);

        fireEvent.click(screen.getByRole('tab', { name: /Upload/ }));

        expect(screen.getByRole('tab', { name: /Upload/ })).toHaveAttribute('aria-selected', 'true');
        expect(screen.getByLabelText('Signature image')).toHaveAttribute('accept', 'image/png,image/jpeg');
        expect(screen.queryByLabelText('Signature pad')).not.toBeInTheDocument();
        expect(onChange).toHaveBeenLastCalledWith(null);
    });

    it('refuses a file that is not a PNG or JPEG, before reading it', async () => {
        render(<SignatureCapture onChange={() => {}} />);
        fireEvent.click(screen.getByRole('tab', { name: /Upload/ }));

        const gif = new File(['GIF89a'], 'sig.gif', { type: 'image/gif' });
        fireEvent.change(screen.getByLabelText('Signature image'), { target: { files: [gif] } });

        expect(await screen.findByText('Choose a PNG or JPEG image.')).toBeInTheDocument();
    });

    it('refuses an image over 5 MB', async () => {
        render(<SignatureCapture onChange={() => {}} />);
        fireEvent.click(screen.getByRole('tab', { name: /Upload/ }));

        const big = new File(['x'], 'sig.png', { type: 'image/png' });
        Object.defineProperty(big, 'size', { value: 6 * 1024 * 1024 });
        fireEvent.change(screen.getByLabelText('Signature image'), { target: { files: [big] } });

        expect(await screen.findByText('That image is over 5 MB. Choose a smaller one.')).toBeInTheDocument();
    });
});
