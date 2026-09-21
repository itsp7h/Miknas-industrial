import { useLocation } from 'react-router-dom';
import { NAV_GROUPS } from './navItems';

/** Every route the menu knows about, mapped to the permission that opens it. */
const PERMISSION_BY_PATH = Object.fromEntries(
    NAV_GROUPS.flatMap((group) => group.items)
        .filter((item) => item.permission || item.adminOnly)
        .map((item) => [item.to, item.permission ?? null])
);

const ADMIN_ONLY_PATHS = new Set(
    NAV_GROUPS.flatMap((group) => group.items)
        .filter((item) => item.adminOnly)
        .map((item) => item.to)
);

/**
 * Hiding a tab from the menu is not the same as closing it.
 *
 * Someone who knows the URL used to land on a page that rendered its chrome and
 * then failed every fetch with a 403 — an empty table reads as "there is
 * nothing here" rather than "this is not yours". The API refuses regardless;
 * this is so the screen says which.
 */
export default function RequirePermission({ isAdmin, permissions = [], children }) {
    const { pathname } = useLocation();

    if (isAdmin) return children;

    const path = pathname;
    const needed = PERMISSION_BY_PATH[path];
    const allowed = ADMIN_ONLY_PATHS.has(path)
        ? false
        : needed === undefined || needed === null || permissions.includes(needed);

    if (allowed) return children;

    return (
        <div style={{
            background: '#fff', border: '1px solid #e2e8f0', borderRadius: 14,
            padding: '2rem', maxWidth: 520,
        }}>
            <h1 style={{ fontSize: 17, fontWeight: 700, color: '#0f172a' }}>Not your page</h1>
            <p style={{ fontSize: 13.5, color: '#64748b', marginTop: 8 }}>
                You do not have access to this part of the system. Ask an administrator
                if you think you should.
            </p>
        </div>
    );
}

export { PERMISSION_BY_PATH, ADMIN_ONLY_PATHS };
