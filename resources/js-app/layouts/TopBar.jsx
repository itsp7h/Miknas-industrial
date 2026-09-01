import NotificationBell from '../components/NotificationBell';
import usePageTitle from './usePageTitle';

const DIVIDER = { width: 1, height: 20, background: '#e2e8f0', flexShrink: 0 };

const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/**
 * PHP's `l, d M Y`, built by hand rather than via toLocaleDateString: en-GB
 * abbreviates September to "Sept", so the locale formatter would not match the
 * Blade topbar it is replacing.
 */
export function formatTopBarDate(date) {
    const day = String(date.getDate()).padStart(2, '0');

    return `${DAYS[date.getDay()]}, ${day} ${MONTHS[date.getMonth()]} ${date.getFullYear()}`;
}

/**
 * The Blade topbar (resources/views/layouts/app.blade.php), value for value:
 * 60px tall, page title on the left, then date · bell · avatar on the right.
 *
 * `compact` is the phone treatment the Blade layout applied by media query —
 * tighter padding, and the date and username dropped because a full date
 * string plus a name never fit a phone row. The avatar stays.
 */
export default function TopBar({ userName, currentUserId, compact = false, onToggleMenu }) {
    const title = usePageTitle();
    const initial = (userName || 'U').charAt(0).toUpperCase();

    return (
        <header style={{
            height: 60, background: '#fff', borderBottom: '1px solid #e2e8f0',
            display: 'flex', alignItems: 'center', gap: compact ? 8 : 16,
            padding: compact ? '0 14px' : '0 28px',
            position: 'sticky', top: 0, zIndex: 30, flexShrink: 0,
            boxShadow: '0 1px 3px rgba(0,0,0,.06)',
        }}>
            {compact && (
                <button
                    aria-label="Menu"
                    onClick={onToggleMenu}
                    style={{
                        background: 'none', border: 'none', cursor: 'pointer',
                        color: '#64748b', padding: 4, display: 'flex', flexShrink: 0,
                    }}
                >
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            )}

            <div style={{ flex: 1, minWidth: 0 }}>
                <span style={{
                    fontSize: 14, fontWeight: 600, color: '#1e293b',
                    display: 'block', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
                }}>
                    {title}
                </span>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: compact ? 8 : 16 }}>
                {!compact && (
                    <>
                        <span style={{ fontSize: 12, color: '#94a3b8', whiteSpace: 'nowrap' }}>
                            {formatTopBarDate(new Date())}
                        </span>
                        <div style={DIVIDER} />
                    </>
                )}

                <NotificationBell currentUserId={currentUserId} />

                {!compact && <div style={DIVIDER} />}

                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <div style={{
                        width: 32, height: 32, flexShrink: 0, borderRadius: '50%', background: '#2563eb',
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                        fontSize: 13, fontWeight: 700, color: '#fff',
                    }}>
                        {initial}
                    </div>
                    {!compact && (
                        <span style={{ fontSize: 13, color: '#475569', fontWeight: 500, whiteSpace: 'nowrap' }}>
                            {userName || 'User'}
                        </span>
                    )}
                </div>
            </div>
        </header>
    );
}
