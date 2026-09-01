import { useState } from 'react';
import { Link } from 'react-router-dom';
import LogoutForm from '../components/LogoutForm';
import { BUILDING, LOGOUT, NavIcon } from './navIcons';
import { DASHBOARD_ITEM, NAV_GROUPS } from './navItems';

/**
 * The Blade sidebar's link, including its two hover behaviours:
 *  - pill links (Dashboard, Pipeline) darken their *background* to #1e293b
 *  - module links lighten their *text* to #e2e8f0
 * Both suppress the hover once the link is active, exactly as the Blade
 * onmouseover guards did.
 */
export function SidebarLink({ item, active, onNavigate }) {
    const [hover, setHover] = useState(false);

    // Two independent axes in the Blade sidebar, which must not be conflated:
    //
    //  layout  — `root` (Dashboard) is a full-width row with an icon; every
    //            other link is indented to 24px, Pipeline included.
    //  colour  — `pill` links (Dashboard, Pipeline) take a blue active fill and
    //            darken their background on hover; module links take a slate
    //            active fill and lighten their text on hover.
    const root = !!item.root;
    const pill = root || !!item.highlight;

    const layout = root
        ? {
            display: 'flex', alignItems: 'center', gap: 10,
            padding: '8px 12px', borderRadius: 8, marginBottom: 2,
            fontSize: 13.5, fontWeight: 500,
        }
        : {
            display: 'flex', alignItems: 'center', gap: 8,
            padding: '7px 12px 7px 24px', borderRadius: 7,
            marginBottom: item.highlight ? 4 : 1,
            fontSize: 13,
            ...(item.highlight ? { fontWeight: 600 } : {}),
        };

    let colour;
    if (pill) {
        colour = active
            ? { background: '#2563eb', color: '#fff' }
            : {
                background: hover ? '#1e293b' : 'transparent',
                color: item.highlight ? '#fbbf24' : '#94a3b8',
            };
    } else {
        colour = active
            ? { background: '#1e293b', color: '#fff', fontWeight: 500 }
            : { color: hover ? '#e2e8f0' : '#94a3b8' };
    }

    const style = {
        ...layout, ...colour,
        textDecoration: 'none', transition: 'background .15s, color .15s',
    };
    const content = (
        <>
            {item.icon && <NavIcon paths={item.icon} size={root ? 16 : 14} style={{ flexShrink: 0 }} />}
            {item.label}
        </>
    );
    const handlers = {
        onMouseEnter: () => setHover(true),
        onMouseLeave: () => setHover(false),
        onClick: onNavigate,
    };

    return item.type === 'link'
        ? <Link to={item.to} style={style} {...handlers}>{content}</Link>
        : <a href={item.to} style={style} {...handlers}>{content}</a>;
}

function SectionHeading({ group }) {
    return (
        <div style={{ marginTop: 16, marginBottom: 4, padding: '0 12px' }}>
            <span style={{
                fontSize: 10, fontWeight: 600, letterSpacing: '.08em', textTransform: 'uppercase',
                color: group.color ?? '#64748b', display: 'flex', alignItems: 'center', gap: 6,
            }}>
                {group.icon && <NavIcon paths={group.icon} size={12} />}
                {group.label}
            </span>
        </div>
    );
}

/**
 * The nav list itself — shared so the desktop sidebar and the mobile drawer
 * cannot drift apart on links, ordering or section colours.
 */
export function SidebarNav({ isAdmin, isActive, onNavigate }) {
    return (
        <>
            <SidebarLink
                item={{ ...DASHBOARD_ITEM, root: true }}
                active={isActive(DASHBOARD_ITEM.to)}
                onNavigate={onNavigate}
            />

            {NAV_GROUPS.filter((group) => !group.adminOnly || isAdmin).map((group) => (
                <div key={group.label}>
                    <SectionHeading group={group} />
                    {group.items.map((item) => (
                        <SidebarLink
                            key={item.to}
                            item={item}
                            active={isActive(item.to)}
                            onNavigate={onNavigate}
                        />
                    ))}
                </div>
            ))}

            <div style={{ height: 16 }} />
        </>
    );
}

function Brand() {
    return (
        <div style={{ padding: '20px 20px 16px', borderBottom: '1px solid #1e293b', flexShrink: 0 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                <div style={{
                    width: 36, height: 36, borderRadius: 10, background: '#2563eb',
                    display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0, color: '#fff',
                }}>
                    <NavIcon paths={BUILDING} size={20} />
                </div>
                <div>
                    <div style={{ color: '#fff', fontWeight: 700, fontSize: 15, lineHeight: 1.2 }}>SteelERP</div>
                    <div style={{ color: '#64748b', fontSize: 11, marginTop: 1 }}>Manufacturing &amp; Trading</div>
                </div>
            </div>
        </div>
    );
}

export function SidebarFooter({ userName, userEmail, logoutUrl, csrfToken }) {
    const [hover, setHover] = useState(false);

    return (
        <div style={{ padding: 12, borderTop: '1px solid #1e293b', flexShrink: 0 }}>
            <div style={{
                display: 'flex', alignItems: 'center', gap: 10,
                padding: '8px 10px', borderRadius: 8, background: '#1e293b',
            }}>
                <div style={{
                    width: 32, height: 32, borderRadius: '50%', background: '#2563eb',
                    display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0,
                    fontSize: 13, fontWeight: 700, color: '#fff',
                }}>
                    {(userName || 'U').charAt(0).toUpperCase()}
                </div>
                <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{
                        color: '#e2e8f0', fontSize: 12.5, fontWeight: 600,
                        whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis',
                    }}>
                        {userName || 'User'}
                    </div>
                    <div style={{
                        color: '#64748b', fontSize: 11,
                        whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis',
                    }}>
                        {userEmail || ''}
                    </div>
                </div>
                {/* Icon-only sign-out, reddening on hover like the Blade button. */}
                <LogoutForm logoutUrl={logoutUrl} csrfToken={csrfToken} style={{ display: 'flex' }}>
                    <span
                        onMouseEnter={() => setHover(true)}
                        onMouseLeave={() => setHover(false)}
                        style={{ display: 'flex', color: hover ? '#f87171' : '#64748b', transition: 'color .15s' }}
                    >
                        <NavIcon paths={LOGOUT} size={15} />
                    </span>
                </LogoutForm>
            </div>
        </div>
    );
}

export default function Sidebar({ isAdmin, isActive, userName, userEmail, logoutUrl, csrfToken }) {
    return (
        <aside style={{
            width: 260, minWidth: 260, background: '#0f172a',
            display: 'flex', flexDirection: 'column', overflowY: 'auto',
        }}>
            <Brand />
            <nav style={{ padding: 12, flex: 1 }}>
                <SidebarNav isAdmin={isAdmin} isActive={isActive} />
            </nav>
            <SidebarFooter
                userName={userName}
                userEmail={userEmail}
                logoutUrl={logoutUrl}
                csrfToken={csrfToken}
            />
        </aside>
    );
}
