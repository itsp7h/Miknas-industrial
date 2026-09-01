import { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import TopBar from './TopBar';
import LogoutForm from '../components/LogoutForm';
import { DASHBOARD_ITEM, NAV_GROUPS } from './navItems';

// Icon paths mirror the section icons used in resources/views/layouts/app.blade.php's
// sidebar, so the bottom bar reads consistently across the legacy Blade pages and
// this React shell.
const TAB_ICONS = {
    Dashboard: 'M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h4a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM14 5a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM14 11a1 1 0 011-1h4a1 1 0 011 1v8a1 1 0 01-1 1h-4a1 1 0 01-1-1v-8z',
    Purchase: 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
    Inventory: 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4',
    Production: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
    Sales: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
};

// One tab per top-level module, pointing at that module's first sidebar link —
// derived from the shared NAV_GROUPS so the destinations never drift from the
// drawer menu above.
const BOTTOM_TABS = [
    { ...DASHBOARD_ITEM, label: 'Dashboard', prefix: '/app' },
    ...NAV_GROUPS.filter((group) => !group.adminOnly).map((group) => ({
        ...group.items[0],
        label: group.label,
        prefix: group.items[0].type === 'link' ? group.items[0].to.replace(/\/[^/]+$/, '') : group.items[0].to,
    })),
];

function BottomTabLink({ tab, active }) {
    const style = {
        flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 2,
        padding: '8px 4px 6px', textDecoration: 'none',
        color: active ? '#2563eb' : '#94a3b8',
    };
    const content = (
        <>
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={TAB_ICONS[tab.label]} />
            </svg>
            <span style={{ fontSize: 10, fontWeight: active ? 700 : 500 }}>{tab.label}</span>
        </>
    );

    return tab.type === 'link'
        ? <Link to={tab.to} style={style}>{content}</Link>
        : <a href={tab.to} style={style}>{content}</a>;
}

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
    const isTabActive = (tab) => (
        tab.label === 'Dashboard' ? location.pathname === '/app' : location.pathname.startsWith(tab.prefix)
    );

    return (
        <div data-testid="mobile-shell" style={{ minHeight: '100vh' }}>
            <TopBar
                userName={userName}
                currentUserId={currentUserId}
                compact
                onToggleMenu={() => setMenuOpen((v) => !v)}
            />

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

            <main style={{ padding: 16, paddingBottom: 84 }}>{children}</main>

            <nav
                data-testid="bottom-tab-bar"
                style={{
                    position: 'fixed', bottom: 0, left: 0, right: 0, zIndex: 45,
                    display: 'flex', background: '#fff', borderTop: '1px solid #e2e8f0',
                    boxShadow: '0 -2px 8px rgba(0,0,0,0.05)', paddingBottom: 'env(safe-area-inset-bottom)',
                }}
            >
                {BOTTOM_TABS.map((tab) => (
                    <BottomTabLink key={tab.label} tab={tab} active={isTabActive(tab)} />
                ))}
            </nav>
        </div>
    );
}
