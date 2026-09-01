import useViewport from '../hooks/useViewport';
import { PageTitleProvider } from './PageTitleContext';
import DesktopShell from './DesktopShell';
import MobileShell from './MobileShell';

export default function AppShell({ children, currentUserId, userName, userEmail, isAdmin, logoutUrl, csrfToken }) {
    const viewport = useViewport();
    const Shell = viewport === 'mobile' ? MobileShell : DesktopShell;

    // The provider sits above the shell so a page can publish a topbar title
    // and TopBar — a sibling of {children} — can read it.
    return (
        <PageTitleProvider>
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
        </PageTitleProvider>
    );
}
