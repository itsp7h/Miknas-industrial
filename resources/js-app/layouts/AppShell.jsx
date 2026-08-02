import useViewport from '../hooks/useViewport';
import DesktopShell from './DesktopShell';
import MobileShell from './MobileShell';

export default function AppShell({ children }) {
    const viewport = useViewport();
    const Shell = viewport === 'mobile' ? MobileShell : DesktopShell;
    return <Shell>{children}</Shell>;
}
