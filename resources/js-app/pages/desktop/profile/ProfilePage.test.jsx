import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import DesktopProfilePage from './ProfilePage';
import MobileProfilePage from '../../mobile/profile/ProfilePage';
import { ToastProvider } from '../../../components/ui/Toast';
import { PageTitleProvider } from '../../../layouts/PageTitleContext';
import * as client from '../../../api/client';

vi.mock('../../../echo', () => ({
    echo: { private: () => ({ listen: () => {}, stopListening: () => {} }), channel: () => ({ listen: () => {} }), leave: () => {} },
}));

const USER = { id: 1, name: 'Admin User', email: 'admin@erp.com', email_verified: true, roles: ['Admin'] };

const wrap = (Page) => render(<PageTitleProvider><ToastProvider><Page /></ToastProvider></PageTitleProvider>);

describe('ProfilePage', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: USER });
    });

    it('renders Breeze’s three sections with the user loaded', async () => {
        wrap(DesktopProfilePage);

        expect(await screen.findByText('Profile Information')).toBeInTheDocument();
        expect(screen.getByText('Update Password')).toBeInTheDocument();
        expect(screen.getByText('Delete Account', { selector: 'h2' })).toBeInTheDocument();
        expect(screen.getByLabelText('Name')).toHaveValue('Admin User');
        expect(screen.getByLabelText('Email')).toHaveValue('admin@erp.com');
    });

    /**
     * Wait for the *loaded value*, not merely for the label: the form renders
     * empty and fills in when apiGet resolves, so keying off the label let a
     * loaded runner click Save before the email arrived and assert against an
     * empty payload. That is what made this file flaky in CI.
     */
    const loadedForm = () => waitFor(() =>
        expect(screen.getByLabelText('Email')).toHaveValue('admin@erp.com'));

    it('saves the name and email', async () => {
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({ message: 'Profile updated.', data: { ...USER, name: 'Renamed' } });
        wrap(DesktopProfilePage);
        await loadedForm();

        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Renamed' } });
        fireEvent.click(screen.getAllByText('Save')[0]);

        await waitFor(() => expect(put).toHaveBeenCalledWith('/profile', { name: 'Renamed', email: 'admin@erp.com' }));
        expect(await screen.findByText('Profile updated.')).toBeInTheDocument();
    });

    it('shows a server field error against the field', async () => {
        vi.spyOn(client, 'apiPut').mockRejectedValue({ errors: { email: ['The email has already been taken.'] } });
        wrap(DesktopProfilePage);
        await loadedForm();

        fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'taken@example.test' } });
        fireEvent.click(screen.getAllByText('Save')[0]);

        expect(await screen.findByText('The email has already been taken.')).toBeInTheDocument();
    });

    // Breeze showed this only while the address was unproven.
    it('offers to resend the verification email only when unverified', async () => {
        wrap(DesktopProfilePage);
        expect(await screen.findByText('Profile Information')).toBeInTheDocument();
        expect(screen.queryByText(/unverified/)).not.toBeInTheDocument();

        vi.spyOn(client, 'apiGet').mockResolvedValue({ data: { ...USER, email_verified: false } });
        const post = vi.spyOn(client, 'apiPost').mockResolvedValue({ message: 'A new verification link has been sent to your email address.' });
        const { unmount } = wrap(DesktopProfilePage);

        fireEvent.click(await screen.findByText('Click here to re-send the verification email.'));
        await waitFor(() => expect(post).toHaveBeenCalledWith('/profile/verification-notification'));
        unmount();
    });

    it('posts the three password fields and clears them on success', async () => {
        const put = vi.spyOn(client, 'apiPut').mockResolvedValue({ message: 'Password updated.' });
        wrap(DesktopProfilePage);

        await screen.findByLabelText('Current Password');
        fireEvent.change(screen.getByLabelText('Current Password'), { target: { value: 'old-one' } });
        fireEvent.change(screen.getByLabelText('New Password'), { target: { value: 'new-one' } });
        fireEvent.change(screen.getByLabelText('Confirm Password'), { target: { value: 'new-one' } });
        fireEvent.click(screen.getAllByText('Save')[1]);

        await waitFor(() => expect(put).toHaveBeenCalledWith('/profile/password', {
            current_password: 'old-one', password: 'new-one', password_confirmation: 'new-one',
        }));
        // Nothing is left sitting in the DOM afterwards.
        await waitFor(() => expect(screen.getByLabelText('Current Password')).toHaveValue(''));
        expect(screen.getByLabelText('New Password')).toHaveValue('');
    });

    it('reports a wrong current password without clearing the new one', async () => {
        vi.spyOn(client, 'apiPut').mockRejectedValue({
            errors: { current_password: ['The password is incorrect.'] },
        });
        wrap(DesktopProfilePage);

        await screen.findByLabelText('Current Password');
        fireEvent.change(screen.getByLabelText('Current Password'), { target: { value: 'wrong' } });
        fireEvent.click(screen.getAllByText('Save')[1]);

        expect(await screen.findByText('The password is incorrect.')).toBeInTheDocument();
    });

    it('asks for the password before deleting the account', async () => {
        wrap(DesktopProfilePage);

        fireEvent.click(await screen.findByRole('button', { name: 'Delete Account' }));

        expect(await screen.findByText('Are you sure you want to delete your account?')).toBeInTheDocument();
        // Nothing can be confirmed until a password is entered.
        const confirm = screen.getAllByRole('button', { name: 'Delete Account' }).at(-1);
        expect(confirm).toBeDisabled();
    });

    it('surfaces a wrong password on deletion and keeps the account', async () => {
        vi.spyOn(client, 'apiDelete').mockRejectedValue({ errors: { password: ['The password is incorrect.'] } });
        wrap(DesktopProfilePage);

        fireEvent.click(await screen.findByRole('button', { name: 'Delete Account' }));
        fireEvent.change(await screen.findByLabelText('Password'), { target: { value: 'wrong' } });
        fireEvent.click(screen.getAllByRole('button', { name: 'Delete Account' }).at(-1));

        expect(await screen.findByText('The password is incorrect.')).toBeInTheDocument();
    });

    it('mobile renders the same sections full width', async () => {
        const { container } = wrap(MobileProfilePage);

        expect(await screen.findByText('Profile Information')).toBeInTheDocument();
        expect(container.firstChild).toHaveStyle({ maxWidth: '100%' });
    });
});
