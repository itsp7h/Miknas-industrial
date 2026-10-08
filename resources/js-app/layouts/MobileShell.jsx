import { useCallback, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import Icon from '../components/mobile/icons';
import { Hero, HeroSlotContext } from '../components/mobile/ui';
import { C } from '../components/mobile/theme';
import usePageTitle from './usePageTitle';
import { usePageTitleOverride } from './PageTitleContext';
import { activeTabKey, mobileTabs, zoneFor } from './mobileTabs';

/**
 * The phone chrome from SteelERP-Mobile-Designs-V2: no top bar and no drawer,
 * just the page and a five-tab bar along the bottom. Each page draws its own
 * coloured header (components/mobile/ui.jsx → Hero); the bell lives on Home
 * and the user card and sign-out on More.
 *
 * A page that has not been given a header of its own still gets one: the
 * shell draws a plain one, titled from the route, until the page claims the
 * slot. Children keep the same position in the tree either way, so claiming
 * never remounts the page.
 */
export default function MobileShell({ children, isAdmin, permissions = [] }) {
    const can = (permission) => permissions.includes(permission);
    const tabs = mobileTabs({ isAdmin, can });
    const { pathname } = useLocation();
    const active = activeTabKey(tabs, pathname);

    const [claims, setClaims] = useState(0);
    const claim = useCallback(() => {
        setClaims((n) => n + 1);

        return () => setClaims((n) => n - 1);
    }, []);

    return (
        <div data-testid="mobile-shell" className="m-ui" style={{ minHeight: '100vh', background: C.bg, color: C.text }}>
            <HeroSlotContext.Provider value={claim}>
                <main style={{ paddingBottom: 'calc(86px + env(safe-area-inset-bottom, 0px))' }}>
                    {claims === 0 && <FallbackHero pathname={pathname} />}
                    <div style={claims === 0 ? { padding: 16 } : undefined}>{children}</div>
                </main>
            </HeroSlotContext.Provider>

            <nav
                aria-label="Main"
                data-testid="bottom-tab-bar"
                style={{
                    position: 'fixed', left: 0, right: 0, bottom: 0, zIndex: 45,
                    display: 'grid', gridTemplateColumns: `repeat(${tabs.length}, minmax(0, 1fr))`,
                    padding: '6px 4px calc(6px + env(safe-area-inset-bottom, 0px))',
                    background: 'rgba(255,255,255,0.94)', backdropFilter: 'blur(18px)',
                    WebkitBackdropFilter: 'blur(18px)', borderTop: `1px solid ${C.line}`,
                }}
            >
                {tabs.map((tab) => {
                    const on = tab.key === active;

                    return (
                        <Link
                            key={tab.key}
                            to={tab.to}
                            aria-current={on ? 'page' : undefined}
                            style={{
                                display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
                                gap: 4, height: 52, textDecoration: 'none', fontSize: 11,
                                fontWeight: on ? 600 : 500, color: on ? C.accent : C.faint,
                            }}
                        >
                            <Icon name={tab.icon} size={24} strokeWidth={on ? 2 : 1.8} />
                            {tab.label}
                        </Link>
                    );
                })}
            </nav>
        </div>
    );
}

function FallbackHero({ pathname }) {
    const routeTitle = usePageTitle();
    const override = usePageTitleOverride();

    // A no-op claim: this header stands in for the page's, so it must not
    // count as the page having one.
    return (
        <HeroSlotContext.Provider value={noop}>
            <div style={{ padding: '0 20px' }}>
                <Hero zone={zoneFor(pathname)} title={override ?? routeTitle} />
            </div>
        </HeroSlotContext.Provider>
    );
}

const noop = () => () => {};
