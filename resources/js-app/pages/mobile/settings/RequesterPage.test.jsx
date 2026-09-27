import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import RequesterPage from './RequesterPage';
import { ToastProvider } from '../../../components/ui/Toast';
import { AccessProvider } from '../../../layouts/AccessContext';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: {
        private: () => ({ listen: () => {}, stopListening: () => {} }),
        channel: () => ({ listen: () => {}, stopListening: () => {} }),
        leave: () => {},
    },
}));

describe('mobile settings RequesterPage', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue({
            data: [{ id: 10, name: 'Ali', company_ids: [1], companies: [{ id: 1, name: 'Miknas Industrial' }] }],
            meta: { companies: [{ id: 1, name: 'Miknas Industrial' }] },
        });
    });

    it('renders the same list and form in the compact layout', async () => {
        render(<ToastProvider><AccessProvider permissions={['requesters.view', 'requesters.create']}>
            <RequesterPage />
        </AccessProvider></ToastProvider>);

        expect(await screen.findByText('Ali')).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Requested By' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Add' })).toBeEnabled();
        expect(screen.getByRole('button', { name: 'Edit' })).toBeDisabled();
    });
});
