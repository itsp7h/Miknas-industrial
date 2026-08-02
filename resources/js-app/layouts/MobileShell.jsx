import { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import NotificationBell from '../components/NotificationBell';

const NAV_ITEMS = [
    { to: '/app', label: 'Dashboard' },
    { to: '/app/purchase/suppliers', label: 'Suppliers' },
];

export default function MobileShell({ children, currentUserId }) {
    const [menuOpen, setMenuOpen] = useState(false);
    const location = useLocation();

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
                    {NAV_ITEMS.map((item) => (
                        <Link
                            key={item.to}
                            to={item.to}
                            onClick={() => setMenuOpen(false)}
                            style={{
                                display: 'block',
                                padding: '12px 16px',
                                textDecoration: 'none',
                                color: location.pathname === item.to ? '#2563eb' : '#334155',
                                fontWeight: location.pathname === item.to ? 600 : 400,
                            }}
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>
            )}

            <main style={{ padding: 16 }}>{children}</main>
        </div>
    );
}
