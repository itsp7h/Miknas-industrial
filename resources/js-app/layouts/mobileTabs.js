// The mobile bottom bar: Home · Pipeline · Purchasing · Inventory · More.
//
// It is not the desktop sidebar folded up. The design gives the two things
// people do most on a phone — move a purchase request along and receive what
// was ordered — a tab each, and puts everything else behind More. A tab is
// offered only when there is a page under it this person can open; each points
// at the first such page, so a tab never leads to a refusal.

import { visibleGroups } from './navItems';

const PURCHASING_PATHS = ['/app/purchase/orders', '/app/purchase/grns', '/app/purchase/invoices'];
const INVENTORY_PATHS = ['/app/inventory/items', '/app/inventory/finished-goods'];

const under = (pathname, paths) => paths.some((p) => pathname === p || pathname.startsWith(`${p}/`));

export function mobileTabs({ isAdmin = false, can = () => false } = {}) {
    const reachable = new Set(
        visibleGroups({ isAdmin, can }).flatMap((group) => group.items.map((item) => item.to)),
    );
    const first = (paths) => paths.find((p) => reachable.has(p));

    return [
        { key: 'home', label: 'Home', icon: 'home', to: '/app', match: (p) => p === '/app' },
        {
            key: 'pipeline', label: 'Pipeline', icon: 'pipeline', to: first(['/app/purchase/pipeline']),
            match: (p) => p.startsWith('/app/purchase/pipeline') || p.startsWith('/app/purchase/requests'),
        },
        {
            key: 'purchasing', label: 'Purchasing', icon: 'cart', to: first(PURCHASING_PATHS),
            match: (p) => under(p, PURCHASING_PATHS),
        },
        {
            key: 'inventory', label: 'Inventory', icon: 'box', to: first(INVENTORY_PATHS),
            // Movements is reached from the Inventory tab's own header, and
            // its back bar leads there.
            match: (p) => under(p, [...INVENTORY_PATHS, '/app/inventory/movements']),
        },
        { key: 'more', label: 'More', icon: 'grid', to: '/app/more', match: () => false },
    ].filter((tab) => tab.to);
}

/** The tab a path belongs to; anything no other tab claims lives under More. */
export function activeTabKey(tabs, pathname) {
    return tabs.find((tab) => tab.match(pathname))?.key ?? 'more';
}

/** Which colour zone a page belongs to, for the fallback header. */
export function zoneFor(pathname) {
    if (pathname.startsWith('/app/purchase')) return 'purchase';
    if (pathname.startsWith('/app/inventory') || pathname.startsWith('/app/production')) return 'inventory';
    if (pathname.startsWith('/app/settings')) return 'system';

    return 'home';
}
