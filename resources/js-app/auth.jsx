import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import useViewport from './hooks/useViewport';
import DesktopConfirmPasswordPage from './pages/desktop/auth/ConfirmPasswordPage';
import DesktopForgotPasswordPage from './pages/desktop/auth/ForgotPasswordPage';
import DesktopLoginPage from './pages/desktop/auth/LoginPage';
import DesktopResetPasswordPage from './pages/desktop/auth/ResetPasswordPage';
import DesktopVerifyEmailPage from './pages/desktop/auth/VerifyEmailPage';
import MobileConfirmPasswordPage from './pages/mobile/auth/ConfirmPasswordPage';
import MobileForgotPasswordPage from './pages/mobile/auth/ForgotPasswordPage';
import MobileLoginPage from './pages/mobile/auth/LoginPage';
import MobileResetPasswordPage from './pages/mobile/auth/ResetPasswordPage';
import MobileVerifyEmailPage from './pages/mobile/auth/VerifyEmailPage';

/**
 * A second Vite entry, separate from main.jsx, because the auth pages are the
 * one place the SPA runs unauthenticated or half-authenticated: /app/{any?}
 * sits behind auth+verified, and these are the screens someone reaches
 * without one or both. This entry carries only what such a visitor needs — no
 * router, no shell, no Echo (which would open a WebSocket for a user who may
 * not exist yet).
 *
 * One entry for all five screens rather than one per page: they share their
 * chrome, their fields and their error handling, and Breeze's routes differ
 * only in which of them to show. The Blade host page says which.
 */
const PAGES = {
    'login': [DesktopLoginPage, MobileLoginPage],
    'forgot-password': [DesktopForgotPasswordPage, MobileForgotPasswordPage],
    'reset-password': [DesktopResetPasswordPage, MobileResetPasswordPage],
    'verify-email': [DesktopVerifyEmailPage, MobileVerifyEmailPage],
    'confirm-password': [DesktopConfirmPasswordPage, MobileConfirmPasswordPage],
};

function AuthApp({ page, props }) {
    const viewport = useViewport();
    const [Desktop, Mobile] = PAGES[page] ?? PAGES.login;
    const Page = viewport === 'mobile' ? Mobile : Desktop;

    return <Page {...props} />;
}

function readProps(container) {
    try {
        return JSON.parse(container.dataset.props || '{}');
    } catch {
        // A malformed payload must not leave a blank page: every screen has
        // sensible defaults, and login needs no props at all.
        return {};
    }
}

const container = document.getElementById('auth-app');

if (container) {
    createRoot(container).render(
        <StrictMode>
            <AuthApp page={container.dataset.page || 'login'} props={readProps(container)} />
        </StrictMode>
    );
}
