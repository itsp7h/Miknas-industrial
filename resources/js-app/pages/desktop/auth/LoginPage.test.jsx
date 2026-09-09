import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import DesktopLoginPage from './LoginPage';
import MobileLoginPage from '../../mobile/auth/LoginPage';
import * as client from '../../../api/client';

// `navigate` is injected instead of letting the page call
// window.location.assign, which jsdom cannot perform.
const navigate = vi.fn();

describe.each([
    ['desktop', DesktopLoginPage],
    ['mobile', MobileLoginPage],
])('LoginPage (%s)', (_name, Page) => {
    beforeEach(() => {
        vi.restoreAllMocks();
        navigate.mockClear();
    });

    it('renders the credential fields and the action', () => {
        render(<Page navigate={navigate} />);

        expect(screen.getByLabelText('Email')).toBeInTheDocument();
        expect(screen.getByLabelText('Password')).toBeInTheDocument();
        expect(screen.getByLabelText('Remember me')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Log in' })).toBeInTheDocument();
        expect(screen.getByText('Forgot your password?').closest('a'))
            .toHaveAttribute('href', '/forgot-password');
    });

    it('posts the credentials and navigates on success', async () => {
        const post = vi.spyOn(client, 'apiPost').mockResolvedValue({ user: {}, roles: [] });
        render(<Page navigate={navigate} />);

        fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'admin@erp.com' } });
        fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'password' } });
        fireEvent.click(screen.getByLabelText('Remember me'));
        fireEvent.click(screen.getByRole('button', { name: 'Log in' }));

        await waitFor(() => expect(post).toHaveBeenCalledWith('/login', {
            email: 'admin@erp.com', password: 'password', remember: true,
        }));
        await waitFor(() => expect(navigate).toHaveBeenCalledWith('/app'));
    });

    it('honours the redirect target from the host page', async () => {
        vi.spyOn(client, 'apiPost').mockResolvedValue({});
        render(<Page navigate={navigate} redirectTo="/app/purchase/pipeline" />);

        fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'a@b.c' } });
        fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'x' } });
        fireEvent.click(screen.getByRole('button', { name: 'Log in' }));

        await waitFor(() => expect(navigate).toHaveBeenCalledWith('/app/purchase/pipeline'));
    });

    it('shows a 422 field error against the field and does not navigate', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({
            errors: { email: ['These credentials do not match our records.'] },
        });
        render(<Page navigate={navigate} />);

        fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'a@b.c' } });
        fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'nope' } });
        fireEvent.click(screen.getByRole('button', { name: 'Log in' }));

        expect(await screen.findByText('These credentials do not match our records.')).toBeInTheDocument();
        expect(navigate).not.toHaveBeenCalled();
    });

    it('surfaces a rate-limit lockout, which arrives on the email field', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({
            errors: { email: ['Too many login attempts. Please try again in 60 seconds.'] },
        });
        render(<Page navigate={navigate} />);

        fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'a@b.c' } });
        fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'x' } });
        fireEvent.click(screen.getByRole('button', { name: 'Log in' }));

        expect(await screen.findByText(/Too many login attempts/)).toBeInTheDocument();
    });

    it('falls back to a form-level message when the failure has no field errors', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({ message: 'Server unavailable.' });
        render(<Page navigate={navigate} />);

        fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'a@b.c' } });
        fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'x' } });
        fireEvent.click(screen.getByRole('button', { name: 'Log in' }));

        expect(await screen.findByRole('alert')).toHaveTextContent('Server unavailable.');
    });

    it('clears a field error once that field is edited again', async () => {
        vi.spyOn(client, 'apiPost').mockRejectedValue({ errors: { email: ['Wrong.'] } });
        render(<Page navigate={navigate} />);

        fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'a@b.c' } });
        fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'x' } });
        fireEvent.click(screen.getByRole('button', { name: 'Log in' }));
        expect(await screen.findByText('Wrong.')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'a@b.co' } });
        expect(screen.queryByText('Wrong.')).not.toBeInTheDocument();
    });

    it('hides the dev quick-login unless the host page enables it', () => {
        const { unmount } = render(<Page navigate={navigate} />);
        expect(screen.queryByText('Dev Quick Login')).not.toBeInTheDocument();
        unmount();

        render(<Page navigate={navigate} showDevLogin />);
        expect(screen.getByText('Dev Quick Login')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Admin' }));
        expect(screen.getByLabelText('Email')).toHaveValue('admin@erp.com');
        expect(screen.getByLabelText('Password')).toHaveValue('password');
    });

    it('disables the action while the request is in flight', async () => {
        let release;
        vi.spyOn(client, 'apiPost').mockReturnValue(new Promise((r) => { release = r; }));
        render(<Page navigate={navigate} />);

        fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'a@b.c' } });
        fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'x' } });
        fireEvent.click(screen.getByRole('button', { name: 'Log in' }));

        const button = await screen.findByRole('button', { name: 'Signing in…' });
        expect(button).toBeDisabled();
        release({});
    });
});
