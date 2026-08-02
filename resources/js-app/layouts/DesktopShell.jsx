import { Link, useLocation } from 'react-router-dom';
import NotificationBell from '../components/NotificationBell';
import LogoutForm from '../components/LogoutForm';
import { DASHBOARD_ITEM, NAV_GROUPS } from './navItems';

function NavLink({ item, active }) {
    const style = {
        display: 'block',
        padding: '8px 12px',
        borderRadius: 8,
        marginBottom: 2,
        fontSize: 13.5,
        fontWeight: 500,
        textDecoration: 'none',
        color: active ? '#fff' : '#94a3b8',
        background: active ? '#2563eb' : 'transparent',
    };

    if (item.type === 'link') {
        return (
            <Link to={item.to} style={style}>
                {item.label}
            </Link>
        );
    }

    return (
        <a href={item.to} style={style}>
            {item.label}
        </a>
    );
}

export default function DesktopShell({ children, currentUserId, userName, userEmail, isAdmin, logoutUrl, csrfToken }) {
    const location = useLocation();
    const isActive = (to) => location.pathname === to;

    return (
        <div data-testid="desktop-shell" style={{ display: 'flex', minHeight: '100vh' }}>
            <aside style={{ width: 260, minWidth: 260, background: '#0f172a', color: '#fff', display: 'flex', flexDirection: 'column' }}>
                <div style={{ padding: '20px 20px 16px', borderBottom: '1px solid #1e293b' }}>
                    <div style={{ fontWeight: 700, fontSize: 15 }}>SteelERP</div>
                    <div style={{ color: '#64748b', fontSize: 11 }}>Manufacturing &amp; Trading</div>
                </div>
                <nav style={{ padding: 12, flex: 1, overflowY: 'auto' }}>
                    <NavLink item={DASHBOARD_ITEM} active={isActive(DASHBOARD_ITEM.to)} />

                    {NAV_GROUPS.filter((group) => !group.adminOnly || isAdmin).map((group) => (
                        <div key={group.label} style={{ marginTop: 16 }}>
                            <div style={{
                                fontSize: 10, fontWeight: 600, letterSpacing: '.08em', textTransform: 'uppercase',
                                color: '#64748b', padding: '0 12px', marginBottom: 4,
                            }}>
                                {group.label}
                            </div>
                            {group.items.map((item) => (
                                <NavLink key={item.to} item={item} active={isActive(item.to)} />
                            ))}
                        </div>
                    ))}
                </nav>
                <div style={{ padding: 12, borderTop: '1px solid #1e293b' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '8px 10px', borderRadius: 8, background: '#1e293b' }}>
                        <div style={{ flex: 1, minWidth: 0 }}>
                            <div style={{ color: '#e2e8f0', fontSize: 12.5, fontWeight: 600, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                                {userName || 'User'}
                            </div>
                            <div style={{ color: '#64748b', fontSize: 11, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                                {userEmail || ''}
                            </div>
                        </div>
                        <LogoutForm logoutUrl={logoutUrl} csrfToken={csrfToken} />
                    </div>
                </div>
            </aside>
            <div style={{ flex: 1, display: 'flex', flexDirection: 'column' }}>
                <header style={{
                    height: 60, borderBottom: '1px solid #e2e8f0',
                    display: 'flex', alignItems: 'center', justifyContent: 'flex-end', padding: '0 24px',
                }}>
                    <NotificationBell currentUserId={currentUserId} />
                </header>
                <main style={{ flex: 1, padding: 24 }}>{children}</main>
            </div>
        </div>
    );
}
