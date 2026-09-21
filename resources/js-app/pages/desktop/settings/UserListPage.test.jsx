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
        { id: 1, name: 'Alan Operations', email: 'alan@example.test', roles: ['Operation Manager'], permissions: ['pipeline.view'] },
        { id: 2, name: 'Zoe Nobody', email: 'zoe@example.test', roles: [], permissions: [] },
    ],
    profiles: [
        { name: 'Admin', description: 'Everything, and the only profile that can manage users.', permissions: [] },
        { name: 'Operation Manager', description: 'Raises purchase requests.', permissions: ['pipeline.view', 'pipeline.create'] },
        { name: 'GM', description: 'Signs off purchase requests.', permissions: ['pipeline.view', 'pipeline.approve'] },
        { name: 'Finance', description: 'Handles invoices and payments.', permissions: [] },
    ],
    grid: [
        {
            tab: 'pipeline', group: 'Purchase', label: 'Pipeline',
            actions: [
                { name: 'pipeline.view', action: 'view', label: 'View' },
                { name: 'pipeline.create', action: 'create', label: 'Create' },
                { name: 'pipeline.edit', action: 'edit', label: 'Edit' },
                { name: 'pipeline.delete', action: 'delete', label: 'Delete' },
            ],
            extra: [{ name: 'pipeline.approve', action: 'approve', label: 'Approve / reject (GM signature)' }],
        },
        {
            tab: 'stock-movements', group: 'Inventory', label: 'Stock Movements',
            actions: [
                { name: 'stock-movements.view', action: 'view', label: 'View' },
                { name: 'stock-movements.create', action: 'create', label: 'Create' },
            ],
            extra: [],
        },
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

    it('shows profiles as pills and says so when a user has none', async () => {
        wrap(DesktopUserListPage);

        await screen.findByText('Alan Operations');
        expect(screen.getByText('Operation Manager')).toBeInTheDocument();
        expect(screen.getByText('No profile')).toBeInTheDocument();
    });

    it('filters client-side with a live count', async () => {
        wrap(DesktopUserListPage);

        await screen.findByText('Alan Operations');
        expect(screen.getByText('2')).toBeInTheDocument();
        fireEvent.change(screen.getByLabelText('Search users'), { target: { value: 'zoe' } });
        expect(screen.getByText('1 of 2')).toBeInTheDocument();
        expect(screen.queryByText('Alan Operations')).not.toBeInTheDocument();
    });

    it('says so when nothing matches the search', async () => {
        wrap(DesktopUserListPage);

        await screen.findByText('Alan Operations');
        fireEvent.change(screen.getByLabelText('Search users'), { target: { value: 'zzz' } });
        expect(screen.getByText('No users match your search.')).toBeInTheDocument();
    });

    it('opens the access modal with the user’s roles and permissions already set', async () => {
        wrap(DesktopUserListPage);

        await screen.findByText('Alan Operations');
        fireEvent.click(screen.getAllByText('Edit Access')[0]);

        expect(await screen.findByText('Edit Access — Alan Operations')).toBeInTheDocument();
        // One profile, chosen — not a checkbox among eight.
        expect(screen.getByRole('radio', { name: /Operation Manager/ })).toBeChecked();
        expect(screen.getByRole('radio', { name: /Admin/ })).not.toBeChecked();
        // The grid: one square per tab and action, ticked from what they hold.
        expect(screen.getByLabelText('Pipeline View')).toBeChecked();
        expect(screen.getByLabelText('Pipeline Delete')).not.toBeChecked();
        // A tab only offers the columns it has — a ledger is never rewritten.
        expect(screen.queryByLabelText('Stock Movements Delete')).not.toBeInTheDocument();
        expect(screen.getByLabelText('Stock Movements Create')).toBeInTheDocument();
    });

    it('saves roles and permissions together', async () => {
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({
            message: 'Access updated for Alan Operations.', data: PAYLOAD.data[0],
        });
        wrap(DesktopUserListPage);

        await screen.findByText('Alan Operations');
        fireEvent.click(screen.getAllByText('Edit Access')[0]);
        fireEvent.click(await screen.findByLabelText('Pipeline Delete'));
        fireEvent.click(screen.getByText('Save'));

        // The single square is granted on top of what they already held.
        await waitFor(() => expect(put).toHaveBeenCalledWith('/settings/users/1', {
            roles: ['Operation Manager'],
            permissions: ['pipeline.view', 'pipeline.delete'],
        }));
    });

    // The server refuses this; the modal has to show why rather than closing.
    it('surfaces the refusal when an admin removes their own Admin role', async () => {
        vi.spyOn(client, 'apiPut').mockRejectedValue({ message: 'You cannot remove your own Admin role.' });
        wrap(DesktopUserListPage);

        await screen.findByText('Alan Operations');
        fireEvent.click(screen.getAllByText('Edit Access')[0]);
        fireEvent.click(await screen.findByText('Save'));

        expect(await screen.findByText('You cannot remove your own Admin role.')).toBeInTheDocument();
        expect(screen.getByText('Edit Access — Alan Operations')).toBeInTheDocument();
    });

    // Blade's two password modes: the fields only exist in manual mode, and the
    // help text says which one is in force.
    it('hides the password fields until "Set password now" is chosen', async () => {
        wrap(DesktopUserListPage);

        await screen.findByText('Alan Operations');
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
            data: { id: 3, name: 'New Person', email: 'new@example.test', roles: ['Operation Manager'], permissions: [] },
        });
        wrap(DesktopUserListPage);

        await screen.findByText('Alan Operations');
        fireEvent.click(screen.getByText('+ New User'));
        fireEvent.change(await screen.findByLabelText('Name'), { target: { value: 'New Person' } });
        fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'new@example.test' } });
        fireEvent.click(screen.getByRole('radio', { name: /Operation Manager/ }));
        fireEvent.click(screen.getByText('Create User'));

        await waitFor(() => expect(post).toHaveBeenCalledWith('/settings/users', {
            name: 'New Person', email: 'new@example.test', roles: ['Operation Manager'], mode: 'email',
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

        await screen.findByText('Alan Operations');
        fireEvent.click(screen.getByText('+ New User'));
        fireEvent.change(await screen.findByLabelText('Email'), { target: { value: 'taken@example.test' } });
        fireEvent.click(screen.getByText('Create User'));

        expect(await screen.findByText('The email has already been taken.')).toBeInTheDocument();
    });

    it('opens the reset-password modal for the chosen user, email mode first', async () => {
        wrap(DesktopUserListPage);

        await screen.findByText('Alan Operations');
        fireEvent.click(screen.getAllByText('Reset Password')[1]);

        expect(await screen.findByRole('heading', { name: 'Reset Password' })).toBeInTheDocument();
        // The dot-prefixed email is the modal's own line, not the table row's.
        expect(screen.getByText(/· zoe@example\.test/)).toBeInTheDocument();
        expect(screen.getByRole('radio', { name: /Email reset link/ })).toBeChecked();
        // The password fields stay out of the way until they are asked for.
        expect(screen.queryByLabelText('New Password')).not.toBeInTheDocument();
    });

    it('emails a reset link without sending a password', async () => {
        const post = vi.spyOn(client, 'apiPost').mockResolvedValue({
            message: 'A password-reset email has been sent to alan@example.test.',
        });
        wrap(DesktopUserListPage);

        await screen.findByText('Alan Operations');
        fireEvent.click(screen.getAllByText('Reset Password')[0]);
        fireEvent.click(await screen.findByText('Send Reset Link'));

        await waitFor(() => expect(post).toHaveBeenCalledWith(
            '/settings/users/1/reset-password', { mode: 'email' }
        ));
        expect(await screen.findByText('A password-reset email has been sent to alan@example.test.')).toBeInTheDocument();
    });

    it('reveals the password fields and sets one directly', async () => {
        const post = vi.spyOn(client, 'apiPost').mockResolvedValue({
            message: 'Password updated for Alan Operations.',
        });
        wrap(DesktopUserListPage);

        await screen.findByText('Alan Operations');
        fireEvent.click(screen.getAllByText('Reset Password')[0]);
        fireEvent.click(await screen.findByRole('radio', { name: /Set password now/ }));

        fireEvent.change(screen.getByLabelText('New Password'), { target: { value: 'CorrectHorseBattery9!' } });
        fireEvent.change(screen.getByLabelText('Confirm Password'), { target: { value: 'CorrectHorseBattery9!' } });
        fireEvent.click(screen.getByText('Set Password'));

        await waitFor(() => expect(post).toHaveBeenCalledWith('/settings/users/1/reset-password', {
            mode: 'password',
            password: 'CorrectHorseBattery9!',
            password_confirmation: 'CorrectHorseBattery9!',
        }));
        expect(await screen.findByText('Password updated for Alan Operations.')).toBeInTheDocument();
    });

    it('shows a server validation error against the password field', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({
            errors: { password: ['The password field must be at least 8 characters.'] },
        });
        wrap(DesktopUserListPage);

        await screen.findByText('Alan Operations');
        fireEvent.click(screen.getAllByText('Reset Password')[0]);
        fireEvent.click(await screen.findByRole('radio', { name: /Set password now/ }));
        fireEvent.change(screen.getByLabelText('New Password'), { target: { value: 'short' } });
        fireEvent.click(screen.getByText('Set Password'));

        expect(await screen.findByText('The password field must be at least 8 characters.')).toBeInTheDocument();
    });

    it('mobile lists users as cards with a full-width add button', async () => {
        wrap(MobileUserListPage);

        const add = await screen.findByText('+ New User');
        expect(add).toHaveStyle({ width: '100%' });
        expect(screen.getByText('Alan Operations')).toBeInTheDocument();
        // The toggles live behind the modal, so the card reports the count.
        expect(screen.getByText('1 individual permission')).toBeInTheDocument();
    });
    /**
     * The point of the grid: whatever profile someone holds, any single square
     * can be handed to them or taken away.
     */
    it('lets one tab be granted in full without touching the rest', async () => {
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({ message: 'Saved.', data: PAYLOAD.data[0] });
        wrap(DesktopUserListPage);

        await screen.findByText('Alan Operations');
        fireEvent.click(screen.getAllByText('Edit Access')[0]);
        fireEvent.click(await screen.findByLabelText('Pipeline all'));
        fireEvent.click(screen.getByText('Save'));

        await waitFor(() => expect(put).toHaveBeenCalledWith('/settings/users/1', {
            roles: ['Operation Manager'],
            permissions: ['pipeline.view', 'pipeline.create', 'pipeline.edit', 'pipeline.delete'],
        }));
    });

    /** Choosing a profile lays its squares down, ready to be adjusted. */
    it('fills the grid in from the chosen profile', async () => {
        wrap(DesktopUserListPage);

        await screen.findByText('Alan Operations');
        fireEvent.click(screen.getAllByText('Edit Access')[0]);
        await screen.findByLabelText('Pipeline View');

        fireEvent.click(screen.getByRole('radio', { name: /GM/ }));

        expect(screen.getByLabelText('Approve / reject (GM signature)')).toBeChecked();
        expect(screen.getByLabelText('Pipeline Create')).not.toBeChecked();
    });

});
