import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
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

    it('renders the list, its Add person button, and each person\'s actions', async () => {
        render(<ToastProvider><AccessProvider permissions={['requesters.view', 'requesters.create']}>
            <MemoryRouter><RequesterPage /></MemoryRouter>
        </AccessProvider></ToastProvider>);

        expect(await screen.findByText('Ali')).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Requested by' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Add person' })).toBeEnabled();
        // Without requesters.edit the person's sheet offers Edit disabled, not missing.
        fireEvent.click(screen.getByRole('button', { name: 'Options for Ali' }));
        expect(screen.getByRole('button', { name: 'Edit person' })).toBeDisabled();
    });
});
