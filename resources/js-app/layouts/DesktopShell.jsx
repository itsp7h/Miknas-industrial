import { Link, useLocation } from 'react-router-dom';
import NotificationBell from '../components/NotificationBell';

const NAV_ITEMS = [
    { to: '/app', label: 'Dashboard' },
    { to: '/app/purchase/suppliers', label: 'Suppliers' },
];

export default function DesktopShell({ children }) {
    const location = useLocation();

    return (
        <div data-testid="desktop-shell" style={{ display: 'flex', minHeight: '100vh' }}>
            <aside style={{ width: 260, minWidth: 260, background: '#0f172a', color: '#fff' }}>
                <div style={{ padding: '20px 20px 16px', borderBottom: '1px solid #1e293b' }}>
                    <div style={{ fontWeight: 700, fontSize: 15 }}>SteelERP</div>
                    <div style={{ color: '#64748b', fontSize: 11 }}>Manufacturing &amp; Trading</div>
                </div>
                <nav style={{ padding: 12 }}>
                    {NAV_ITEMS.map((item) => (
                        <Link
                            key={item.to}
                            to={item.to}
                            style={{
                                display: 'block',
                                padding: '8px 12px',
                                borderRadius: 8,
                                marginBottom: 2,
                                fontSize: 13.5,
                                fontWeight: 500,
                                textDecoration: 'none',
                                color: location.pathname === item.to ? '#fff' : '#94a3b8',
                                background: location.pathname === item.to ? '#2563eb' : 'transparent',
                            }}
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>
            </aside>
            <div style={{ flex: 1, display: 'flex', flexDirection: 'column' }}>
                <header style={{
                    height: 60, borderBottom: '1px solid #e2e8f0',
                    display: 'flex', alignItems: 'center', justifyContent: 'flex-end', padding: '0 24px',
                }}>
                    <NotificationBell />
                </header>
                <main style={{ flex: 1, padding: 24 }}>{children}</main>
            </div>
        </div>
    );
}
