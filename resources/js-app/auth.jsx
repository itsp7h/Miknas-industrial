import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import useViewport from './hooks/useViewport';
import DesktopLoginPage from './pages/desktop/auth/LoginPage';
import MobileLoginPage from './pages/mobile/auth/LoginPage';

/**
 * A second Vite entry, separate from main.jsx, because the auth pages are the
 * one place the SPA runs unauthenticated: /app/{any?} sits behind auth+verified,
 * so the login screen cannot be a route inside that shell. This entry carries
 * only what a guest needs — no router, no shell, no Echo (which would open a
 * WebSocket for a user who does not exist yet).
 */
function AuthApp({ redirectTo, showDevLogin }) {
    const viewport = useViewport();
    const LoginPage = viewport === 'mobile' ? MobileLoginPage : DesktopLoginPage;

    return <LoginPage redirectTo={redirectTo} showDevLogin={showDevLogin} />;
}

const container = document.getElementById('auth-app');

if (container) {
    createRoot(container).render(
        <StrictMode>
            <AuthApp
                redirectTo={container.dataset.redirectTo || '/app'}
                showDevLogin={container.dataset.showDevLogin === '1'}
            />
        </StrictMode>
    );
}
