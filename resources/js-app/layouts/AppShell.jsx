import useViewport from '../hooks/useViewport';
import DesktopShell from './DesktopShell';
import MobileShell from './MobileShell';

export default function AppShell({ children, currentUserId }) {
    const viewport = useViewport();
    const Shell = viewport === 'mobile' ? MobileShell : DesktopShell;
    return <Shell currentUserId={currentUserId}>{children}</Shell>;
}
