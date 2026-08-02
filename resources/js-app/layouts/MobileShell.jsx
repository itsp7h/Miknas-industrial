import { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import NotificationBell from '../components/NotificationBell';
import LogoutForm from '../components/LogoutForm';
import { DASHBOARD_ITEM, NAV_GROUPS } from './navItems';

function NavLink({ item, active, onNavigate }) {
    const style = {
        display: 'block',
        padding: '12px 16px',
        textDecoration: 'none',
        color: active ? '#2563eb' : '#334155',
        fontWeight: active ? 600 : 400,
    };

    if (item.type === 'link') {
        return (
            <Link to={item.to} onClick={onNavigate} style={style}>
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

export default function MobileShell({ children, currentUserId, userName, userEmail, isAdmin, logoutUrl, csrfToken }) {
    const [menuOpen, setMenuOpen] = useState(false);
    const location = useLocation();
    const isActive = (to) => location.pathname === to;

    return (
        <div data-testid="mobile-shell" style={{ minHeight: '100vh' }}>
            <header style={{
                display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                padding: '12px 16px', borderBottom: '1px solid #e2e8f0',
            }}>
                <button aria-label="Menu" onClick={() => setMenuOpen((v) => !v)} style={{ fontSize: 20 }}>
                    ☰
                </button>
                <span style={{ fontWeight: 700 }}>SteelERP</span>
                <NotificationBell currentUserId={currentUserId} />
            </header>

            {menuOpen && (
                <nav style={{ borderBottom: '1px solid #e2e8f0' }}>
                    <NavLink item={DASHBOARD_ITEM} active={isActive(DASHBOARD_ITEM.to)} onNavigate={() => setMenuOpen(false)} />

                    {NAV_GROUPS.filter((group) => !group.adminOnly || isAdmin).map((group) => (
                        <div key={group.label}>
                            <div style={{
                                fontSize: 11, fontWeight: 600, letterSpacing: '.08em', textTransform: 'uppercase',
                                color: '#94a3b8', padding: '10px 16px 2px',
                            }}>
                                {group.label}
                            </div>
                            {group.items.map((item) => (
                                <NavLink key={item.to} item={item} active={isActive(item.to)} onNavigate={() => setMenuOpen(false)} />
                            ))}
                        </div>
                    ))}

                    <div style={{ padding: '12px 16px', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                        <div style={{ fontSize: 12, color: '#64748b' }}>{userName}{userEmail ? ` · ${userEmail}` : ''}</div>
                        <LogoutForm logoutUrl={logoutUrl} csrfToken={csrfToken} />
                    </div>
                </nav>
            )}

            <main style={{ padding: 16 }}>{children}</main>
        </div>
    );
}
