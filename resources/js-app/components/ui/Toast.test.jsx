import { describe, it, expect } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { ToastProvider, useToast } from './Toast';

function Trigger() {
    const { showToast } = useToast();
    return <button onClick={() => showToast('Saved.', 'success')}>Trigger</button>;
}

describe('ToastProvider', () => {
    it('renders a toast when showToast is called', async () => {
        render(
            <ToastProvider>
                <Trigger />
            </ToastProvider>
        );

        screen.getByText('Trigger').click();

        await waitFor(() => expect(screen.getByText('Saved.')).toBeInTheDocument());
    });
});
