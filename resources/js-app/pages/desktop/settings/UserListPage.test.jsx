import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import DesktopUserListPage from './UserListPage';
import MobileUserListPage from '../../mobile/settings/UserListPage';
import { ToastProvider } from '../../../components/ui/Toast';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => {}, stopListening: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const PAYLOAD = {
    data: [
        { id: 1, name: 'Alan Requester', email: 'alan@example.test', roles: ['Requester'], permissions: ['purchase-requests.view-all'] },
        { id: 2, name: 'Zoe Nobody', email: 'zoe@example.test', roles: [], permissions: [] },
    ],
    roles: ['Admin', 'Requester'],
    permissions: [
        { name: 'purchase-requests.create', label: 'Create purchase requests' },
        { name: 'purchase-requests.view-all', label: 'View all purchase requests (monitoring)' },
    ],
};

const wrap = (Page) => render(<ToastProvider><Page /></ToastProvider>);

describe('settings UserListPage', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue(PAYLOAD);
    });

    it('renders the header and Blade’s four columns', async () => {
        wrap(DesktopUserListPage);

        expect(await screen.findByText('User Management')).toBeInTheDocument();
        expect(screen.getByText('Assign profiles and toggle individual permissions for each employee.')).toBeInTheDocument();
        expect(screen.getAllByRole('columnheader').map((th) => th.textContent)).toEqual(['Name', 'Email', 'Profiles', '']);
        expect(screen.getByText('alan@example.test')).toBeInTheDocument();
    });

    it('shows roles as pills and says so when a user has none', async () => {
        wrap(DesktopUserListPage);

        await screen.findByText('Alan Requester');
        expect(screen.getByText('Requester')).toBeInTheDocument();
        expect(screen.getByText('No profile')).toBeInTheDocument();
    });

    it('filters client-side with a live count', async () => {
        wrap(DesktopUserListPage);

        await screen.findByText('Alan Requester');
        expect(screen.getByText('2')).toBeInTheDocument();
        fireEvent.change(screen.getByLabelText('Search users'), { target: { value: 'zoe' } });
        expect(screen.getByText('1 of 2')).toBeInTheDocument();
        expect(screen.queryByText('Alan Requester')).not.toBeInTheDocument();
    });

    it('says so when nothing matches the search', async () => {
        wrap(DesktopUserListPage);

        await screen.findByText('Alan Requester');
        fireEvent.change(screen.getByLabelText('Search users'), { target: { value: 'zzz' } });
        expect(screen.getByText('No users match your search.')).toBeInTheDocument();
    });

    it('opens the access modal with the user’s roles and permissions already set', async () => {
        wrap(DesktopUserListPage);

        await screen.findByText('Alan Requester');
        fireEvent.click(screen.getAllByText('Edit Access')[0]);

        expect(await screen.findByText('Edit Access — Alan Requester')).toBeInTheDocument();
        expect(screen.getByRole('checkbox', { name: 'Requester' })).toBeChecked();
        expect(screen.getByRole('checkbox', { name: 'Admin' })).not.toBeChecked();
        // Toggles, not checkboxes-with-labels: the permission reads by its label.
        expect(screen.getByLabelText('View all purchase requests (monitoring)')).toBeChecked();
        expect(screen.getByLabelText('Create purchase requests')).not.toBeChecked();
    });

    it('saves roles and permissions together', async () => {
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({
            message: 'Access updated for Alan Requester.', data: PAYLOAD.data[0],
        });
        wrap(DesktopUserListPage);

        await screen.findByText('Alan Requester');
        fireEvent.click(screen.getAllByText('Edit Access')[0]);
        fireEvent.click(await screen.findByLabelText('Create purchase requests'));
        fireEvent.click(screen.getByText('Save'));

        await waitFor(() => expect(put).toHaveBeenCalledWith('/settings/users/1', {
            roles: ['Requester'],
            permissions: ['purchase-requests.view-all', 'purchase-requests.create'],
        }));
    });

    // The server refuses this; the modal has to show why rather than closing.
    it('surfaces the refusal when an admin removes their own Admin role', async () => {
        vi.spyOn(client, 'apiPut').mockRejectedValue({ message: 'You cannot remove your own Admin role.' });
        wrap(DesktopUserListPage);

        await screen.findByText('Alan Requester');
        fireEvent.click(screen.getAllByText('Edit Access')[0]);
        fireEvent.click(await screen.findByText('Save'));

        expect(await screen.findByText('You cannot remove your own Admin role.')).toBeInTheDocument();
        expect(screen.getByText('Edit Access — Alan Requester')).toBeInTheDocument();
    });

    // Blade's two password modes: the fields only exist in manual mode, and the
    // help text says which one is in force.
    it('hides the password fields until "Set password now" is chosen', async () => {
        wrap(DesktopUserListPage);

        await screen.findByText('Alan Requester');
        fireEvent.click(screen.getByText('+ New User'));

        expect(await screen.findByText(/receive an email with a link/)).toBeInTheDocument();
        expect(screen.queryByLabelText('Password')).not.toBeInTheDocument();

        fireEvent.click(screen.getByRole('radio', { name: 'Set password now' }));
        expect(screen.getByLabelText('Password')).toBeInTheDocument();
        expect(screen.getByLabelText('Confirm Password')).toBeInTheDocument();
        expect(screen.getByText(/no email will be sent/)).toBeInTheDocument();
    });

    it('creates a user in email mode without sending a password', async () => {
        const post = vi.spyOn(client, 'apiPost').mockResolvedValue({
            message: 'New Person created. A password-setup email has been sent.',
            data: { id: 3, name: 'New Person', email: 'new@example.test', roles: ['Requester'], permissions: [] },
        });
        wrap(DesktopUserListPage);

        await screen.findByText('Alan Requester');
        fireEvent.click(screen.getByText('+ New User'));
        fireEvent.change(await screen.findByLabelText('Name'), { target: { value: 'New Person' } });
        fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'new@example.test' } });
        fireEvent.click(screen.getByRole('checkbox', { name: 'Requester' }));
        fireEvent.click(screen.getByText('Create User'));

        await waitFor(() => expect(post).toHaveBeenCalledWith('/settings/users', {
            name: 'New Person', email: 'new@example.test', roles: ['Requester'], mode: 'email',
        }));
        // The server's own wording — it knows whether the email went out.
        expect(await screen.findByText('New Person created. A password-setup email has been sent.')).toBeInTheDocument();
        expect(screen.getByText('New Person')).toBeInTheDocument();
    });

    it('shows a field error from the server against the field', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({
            errors: { email: ['The email has already been taken.'] },
        });
        wrap(DesktopUserListPage);

        await screen.findByText('Alan Requester');
        fireEvent.click(screen.getByText('+ New User'));
        fireEvent.change(await screen.findByLabelText('Email'), { target: { value: 'taken@example.test' } });
        fireEvent.click(screen.getByText('Create User'));

        expect(await screen.findByText('The email has already been taken.')).toBeInTheDocument();
    });

    it('mobile lists users as cards with a full-width add button', async () => {
        wrap(MobileUserListPage);

        const add = await screen.findByText('+ New User');
        expect(add).toHaveStyle({ width: '100%' });
        expect(screen.getByText('Alan Requester')).toBeInTheDocument();
        // The toggles live behind the modal, so the card reports the count.
        expect(screen.getByText('1 individual permission')).toBeInTheDocument();
    });
});
