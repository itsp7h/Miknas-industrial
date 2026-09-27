import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
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

const MIKNAS = { id: 1, name: 'Miknas Industrial' };
const STEEL_TECH = { id: 2, name: 'Steel Tech' };

const PEOPLE = [
    { id: 10, name: 'Ali', company_ids: [1, 2], companies: [MIKNAS, STEEL_TECH] },
    { id: 11, name: 'Zainab', company_ids: [2], companies: [STEEL_TECH] },
];

const ALL = ['requesters.view', 'requesters.create', 'requesters.edit', 'requesters.delete'];

const renderPage = (permissions = ALL) => render(
    <ToastProvider><AccessProvider permissions={permissions}><RequesterPage /></AccessProvider></ToastProvider>
);

describe('settings RequesterPage (System → Requested By)', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: PEOPLE, meta: { companies: [MIKNAS, STEEL_TECH] } });
    });

    it('lists each person with the companies they request for', async () => {
        renderPage();
        expect(await screen.findByText('Ali')).toBeInTheDocument();
        expect(screen.getByText('2 people')).toBeInTheDocument();

        const ali = screen.getByText('Ali').closest('div').parentElement;
        expect(within(ali).getByText('Miknas Industrial')).toBeInTheDocument();
        expect(within(ali).getByText('Steel Tech')).toBeInTheDocument();
    });

    it('adds a person to several companies and shows them without a reload', async () => {
        const post = vi.spyOn(client, 'apiPost').mockResolvedValue({
            data: { id: 12, name: 'Omar', company_ids: [1, 2], companies: [MIKNAS, STEEL_TECH] },
            message: 'Omar added.',
        });
        renderPage();
        await screen.findByText('Ali');

        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Omar' } });
        fireEvent.click(screen.getByLabelText('Miknas Industrial'));
        fireEvent.click(screen.getByLabelText('Steel Tech'));
        fireEvent.click(screen.getByRole('button', { name: 'Add' }));

        await waitFor(() => expect(post).toHaveBeenCalledWith('/settings/requesters', { name: 'Omar', company_ids: [1, 2] }));
        expect(await screen.findByText('Omar')).toBeInTheDocument();
        expect(screen.getByText('Omar added.')).toBeInTheDocument();
        // The form resets for the next person.
        expect(screen.getByLabelText('Name')).toHaveValue('');
        expect(screen.getByLabelText('Miknas Industrial')).not.toBeChecked();
    });

    it('edits a person: loads their companies, and saves the change', async () => {
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({
            data: { id: 11, name: 'Zainab Ali', company_ids: [1], companies: [MIKNAS] },
            message: 'Zainab Ali saved.',
        });
        renderPage();
        await screen.findByText('Zainab');

        const zainab = screen.getByText('Zainab').closest('div').parentElement.parentElement;
        fireEvent.click(within(zainab).getByRole('button', { name: 'Edit' }));

        expect(screen.getByText('Edit Zainab')).toBeInTheDocument();
        expect(screen.getByLabelText('Steel Tech')).toBeChecked();
        expect(screen.getByLabelText('Miknas Industrial')).not.toBeChecked();

        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Zainab Ali' } });
        fireEvent.click(screen.getByLabelText('Steel Tech'));
        fireEvent.click(screen.getByLabelText('Miknas Industrial'));
        fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        await waitFor(() => expect(put).toHaveBeenCalledWith('/settings/requesters/11', { name: 'Zainab Ali', company_ids: [1] }));
        expect(await screen.findByText('Zainab Ali')).toBeInTheDocument();
    });

    it('puts every company error under the checkboxes', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({
            message: 'Invalid.', errors: { company_ids: ['Choose at least one company.'] },
        });
        renderPage();
        await screen.findByText('Ali');

        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Omar' } });
        fireEvent.click(screen.getByRole('button', { name: 'Add' }));

        expect(await screen.findByText('Choose at least one company.')).toBeInTheDocument();
    });

    it('removes a person after confirming', async () => {
        const del = vi.spyOn(client, 'apiDelete').mockResolvedValue({ deleted: true, id: 10, message: 'Ali removed.' });
        renderPage();
        await screen.findByText('Ali');

        fireEvent.click(screen.getAllByRole('button', { name: 'Delete' })[0]);
        expect(screen.getByText(/will no longer be offered as Requested By/)).toBeInTheDocument();
        fireEvent.click(within(screen.getByText('Remove this person?').closest('div').parentElement).getAllByRole('button').at(-1));

        await waitFor(() => expect(del).toHaveBeenCalledWith('/settings/requesters/10'));
        await waitFor(() => expect(screen.queryByText('Ali')).not.toBeInTheDocument());
    });

    it('filters the list as you type, by name or company', async () => {
        renderPage();
        await screen.findByText('Ali');

        fireEvent.change(screen.getByLabelText('Search people'), { target: { value: 'miknas' } });
        expect(screen.getByText('Ali')).toBeInTheDocument();
        expect(screen.queryByText('Zainab')).not.toBeInTheDocument();
        expect(screen.getByText('1 of 2 people')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Search people'), { target: { value: 'nobody' } });
        expect(screen.getByText(/No one matches/)).toBeInTheDocument();
    });

    // CLAUDE.md #14: what the viewer may not do stays on the page, disabled.
    it('shows the actions disabled, with the reason, to a view-only user', async () => {
        renderPage(['requesters.view']);
        await screen.findByText('Ali');

        const add = screen.getByRole('button', { name: 'Add' });
        expect(add).toBeDisabled();
        expect(add).toHaveAttribute('title', 'You do not have permission to add people on this list');
        expect(screen.getByLabelText('Name')).toBeDisabled();
        expect(screen.getByLabelText('Miknas Industrial')).toBeDisabled();

        screen.getAllByRole('button', { name: 'Edit' }).forEach((b) => expect(b).toBeDisabled());
        screen.getAllByRole('button', { name: 'Delete' }).forEach((b) => {
            expect(b).toBeDisabled();
            expect(b).toHaveAttribute('title', 'You do not have permission to remove people on this list');
        });
    });
});
