import { useLocation } from 'react-router-dom';
import Sidebar from './Sidebar';
import TopBar from './TopBar';

export default function DesktopShell({ children, currentUserId, userName, userEmail, isAdmin, logoutUrl, csrfToken }) {
    const location = useLocation();
    // A detail route keeps its list link highlighted, matching the Blade
    // sidebar's request()->is('...*') prefix matching.
    const isActive = (to) => location.pathname === to
        || (to !== '/app' && location.pathname.startsWith(`${to}/`));

    return (
        <div data-testid="desktop-shell" style={{ display: 'flex', minHeight: '100vh' }}>
            <Sidebar
                isAdmin={isAdmin}
                isActive={isActive}
                userName={userName}
                userEmail={userEmail}
                logoutUrl={logoutUrl}
                csrfToken={csrfToken}
            />
            <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
                <TopBar userName={userName} currentUserId={currentUserId} />
                {/* Matches the Blade layout's <main>: 28px padding on a #f1f5f9 ground. */}
                <main style={{ flex: 1, padding: 28, background: '#f1f5f9', minWidth: 0, overflowX: 'hidden' }}>
                    {children}
                </main>
            </div>
        </div>
    );
}
