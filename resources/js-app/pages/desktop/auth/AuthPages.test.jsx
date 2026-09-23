import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import DesktopForgotPassword from './ForgotPasswordPage';
import MobileForgotPassword from '../../mobile/auth/ForgotPasswordPage';
import DesktopResetPassword from './ResetPasswordPage';
import MobileResetPassword from '../../mobile/auth/ResetPasswordPage';
import DesktopConfirmPassword from './ConfirmPasswordPage';
import MobileConfirmPassword from '../../mobile/auth/ConfirmPasswordPage';
import DesktopVerifyEmail from './VerifyEmailPage';
import MobileVerifyEmail from '../../mobile/auth/VerifyEmailPage';

/**
 * The four auth screens that are not login, both viewports each, driven from
 * one file the way LoginPage.test.jsx does it. `post` is injected rather than
 * mocking the api client, so each test says what the server answered.
 *
 * `navigate` is injected too: jsdom cannot perform window.location.assign.
 */
let post;
let navigate;

beforeEach(() => {
    vi.restoreAllMocks();
    post = vi.fn();
    navigate = vi.fn();
});

describe.each([
    ['desktop', DesktopForgotPassword],
    ['mobile', MobileForgotPassword],
])('ForgotPasswordPage (%s)', (_name, Page) => {
    it('asks for the address and reports that the link was sent', async () => {
        post.mockResolvedValue({ message: 'We have emailed your password reset link.' });
        render(<Page post={post} />);

        fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'admin@erp.com' } });
        fireEvent.click(screen.getByRole('button', { name: 'Email password reset link' }));

        await waitFor(() => expect(post).toHaveBeenCalledWith('/forgot-password', { email: 'admin@erp.com' }));
        expect(await screen.findByRole('status')).toHaveTextContent('We have emailed your password reset link.');
    });

    it('shows the brokers refusal against the email field', async () => {
        post.mockRejectedValue({ errors: { email: ["We can't find a user with that email address."] } });
        render(<Page post={post} />);

        fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'nobody@erp.com' } });
        fireEvent.click(screen.getByRole('button', { name: 'Email password reset link' }));

        expect(await screen.findByText(/can't find a user/)).toBeInTheDocument();
        expect(screen.queryByRole('status')).not.toBeInTheDocument();
    });

    it('offers the way back to sign in', () => {
        render(<Page post={post} />);

        expect(screen.getByText('Back to sign in').closest('a')).toHaveAttribute('href', '/login');
    });
});

describe.each([
    ['desktop', DesktopResetPassword],
    ['mobile', MobileResetPassword],
])('ResetPasswordPage (%s)', (_name, Page) => {
    const mount = () => render(
        <Page token="reset-token-123" email="admin@erp.com" post={post} />
    );

    it('carries the token and address from the emailed link', async () => {
        post.mockResolvedValue({ message: 'Your password has been reset.' });
        mount();

        expect(screen.getByLabelText('Email')).toHaveValue('admin@erp.com');

        fireEvent.change(screen.getByLabelText('New password'), { target: { value: 'new-password-123' } });
        fireEvent.change(screen.getByLabelText('Confirm new password'), { target: { value: 'new-password-123' } });
        fireEvent.click(screen.getByRole('button', { name: 'Reset password' }));

        await waitFor(() => expect(post).toHaveBeenCalledWith('/reset-password', {
            token: 'reset-token-123',
            email: 'admin@erp.com',
            password: 'new-password-123',
            password_confirmation: 'new-password-123',
        }));
    });

    /**
     * Breeze flashed a message onto the login page; a fetch cannot carry one
     * across a full load, so the outcome is stated here with the way onward.
     */
    it('states the outcome and points at sign in rather than flashing', async () => {
        post.mockResolvedValue({ message: 'Your password has been reset.' });
        mount();

        fireEvent.change(screen.getByLabelText('New password'), { target: { value: 'new-password-123' } });
        fireEvent.change(screen.getByLabelText('Confirm new password'), { target: { value: 'new-password-123' } });
        fireEvent.click(screen.getByRole('button', { name: 'Reset password' }));

        expect(await screen.findByRole('status')).toHaveTextContent('Your password has been reset.');
        expect(screen.getByText('Continue to sign in').closest('a')).toHaveAttribute('href', '/login');
        expect(screen.queryByLabelText('New password')).not.toBeInTheDocument();
    });

    it('shows a stale-token failure against the email field and keeps the form', async () => {
        post.mockRejectedValue({ errors: { email: ['This password reset token is invalid.'] } });
        mount();

        fireEvent.change(screen.getByLabelText('New password'), { target: { value: 'new-password-123' } });
        fireEvent.change(screen.getByLabelText('Confirm new password'), { target: { value: 'new-password-123' } });
        fireEvent.click(screen.getByRole('button', { name: 'Reset password' }));

        expect(await screen.findByText('This password reset token is invalid.')).toBeInTheDocument();
        expect(screen.getByLabelText('New password')).toBeInTheDocument();
    });

    it('shows a rejected password against the password field', async () => {
        post.mockRejectedValue({ errors: { password: ['The password field must be at least 8 characters.'] } });
        mount();

        fireEvent.change(screen.getByLabelText('New password'), { target: { value: 'short' } });
        fireEvent.change(screen.getByLabelText('Confirm new password'), { target: { value: 'short' } });
        fireEvent.click(screen.getByRole('button', { name: 'Reset password' }));

        expect(await screen.findByText(/at least 8 characters/)).toBeInTheDocument();
    });
});

describe.each([
    ['desktop', DesktopConfirmPassword],
    ['mobile', MobileConfirmPassword],
])('ConfirmPasswordPage (%s)', (_name, Page) => {
    it('confirms and then goes where the server said', async () => {
        post.mockResolvedValue({ redirect_to: '/app/settings/users' });
        render(<Page post={post} navigate={navigate} />);

        fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'password' } });
        fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));

        await waitFor(() => expect(post).toHaveBeenCalledWith('/confirm-password', { password: 'password' }));
        await waitFor(() => expect(navigate).toHaveBeenCalledWith('/app/settings/users'));
    });

    it('falls back to the app when no destination came back', async () => {
        post.mockResolvedValue({});
        render(<Page post={post} navigate={navigate} />);

        fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'password' } });
        fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));

        await waitFor(() => expect(navigate).toHaveBeenCalledWith('/app'));
    });

    it('shows a wrong password against the field and does not navigate', async () => {
        post.mockRejectedValue({ errors: { password: ['The provided password is incorrect.'] } });
        render(<Page post={post} navigate={navigate} />);

        fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'nope' } });
        fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));

        expect(await screen.findByText('The provided password is incorrect.')).toBeInTheDocument();
        expect(navigate).not.toHaveBeenCalled();
    });
});

describe.each([
    ['desktop', DesktopVerifyEmail],
    ['mobile', MobileVerifyEmail],
])('VerifyEmailPage (%s)', (_name, Page) => {
    it('shows which address is waiting on a link', () => {
        render(<Page email="admin@erp.com" post={post} navigate={navigate} />);

        expect(screen.getByText('admin@erp.com')).toBeInTheDocument();
    });

    it('resends the verification email and reports it', async () => {
        post.mockResolvedValue({ message: 'A new verification link has been sent to your email address.' });
        render(<Page email="admin@erp.com" post={post} navigate={navigate} />);

        fireEvent.click(screen.getByRole('button', { name: 'Resend verification email' }));

        await waitFor(() => expect(post).toHaveBeenCalledWith('/profile/verification-notification', {}));
        expect(await screen.findByRole('status')).toHaveTextContent('A new verification link has been sent');
    });

    it('signs out and returns to the front door', async () => {
        post.mockResolvedValue(null);
        render(<Page email="admin@erp.com" post={post} navigate={navigate} />);

        fireEvent.click(screen.getByRole('button', { name: 'Log out' }));

        await waitFor(() => expect(post).toHaveBeenCalledWith('/logout', {}));
        await waitFor(() => expect(navigate).toHaveBeenCalledWith('/'));
    });

    it('reports a failed resend rather than pretending it worked', async () => {
        post.mockRejectedValue({ message: 'Too many requests.' });
        render(<Page email="admin@erp.com" post={post} navigate={navigate} />);

        fireEvent.click(screen.getByRole('button', { name: 'Resend verification email' }));

        expect(await screen.findByRole('alert')).toHaveTextContent('Too many requests.');
        expect(screen.queryByRole('status')).not.toBeInTheDocument();
    });
});
