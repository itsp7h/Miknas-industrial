import useViewport from '../hooks/useViewport';
import { PageTitleProvider } from './PageTitleContext';
import DesktopShell from './DesktopShell';
import MobileShell from './MobileShell';
import { ShellUserProvider } from './ShellUserContext';

export default function AppShell({ children, currentUserId, userName, userEmail, userRole = '', isAdmin, permissions = [], logoutUrl, csrfToken }) {
    const viewport = useViewport();
    const Shell = viewport === 'mobile' ? MobileShell : DesktopShell;

    // The provider sits above the shell so a page can publish a topbar title
    // and TopBar — a sibling of {children} — can read it.
    const user = { currentUserId, userName, userEmail, userRole, logoutUrl, csrfToken };

    return (
        <ShellUserProvider value={user}>
        <PageTitleProvider>
            <Shell
                currentUserId={currentUserId}
                userName={userName}
                userEmail={userEmail}
                isAdmin={isAdmin}
                permissions={permissions}
                logoutUrl={logoutUrl}
                csrfToken={csrfToken}
            >
                {children}
            </Shell>
        </PageTitleProvider>
        </ShellUserProvider>
    );
}
