import useViewport from '../hooks/useViewport';
import DesktopShell from './DesktopShell';
import MobileShell from './MobileShell';

export default function AppShell({ children, currentUserId, userName, userEmail, isAdmin, logoutUrl, csrfToken }) {
    const viewport = useViewport();
    const Shell = viewport === 'mobile' ? MobileShell : DesktopShell;
    return (
        <Shell
            currentUserId={currentUserId}
            userName={userName}
            userEmail={userEmail}
            isAdmin={isAdmin}
            logoutUrl={logoutUrl}
            csrfToken={csrfToken}
        >
            {children}
        </Shell>
    );
}
