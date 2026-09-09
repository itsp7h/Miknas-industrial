import { useLocation } from 'react-router-dom';
import { DASHBOARD_ITEM, NAV_GROUPS } from './navItems';

// The Blade topbar showed @yield('title'). React has no per-page yield, so the
// title is derived from the shared nav structure by path — one source of truth,
// and a new page picks its title up automatically once it is in navItems.
const TITLES = [
    [DASHBOARD_ITEM.to, DASHBOARD_ITEM.label],
    ...NAV_GROUPS.flatMap((group) => group.items.map((item) => [item.to, item.label])),
];

export default function usePageTitle() {
    const { pathname } = useLocation();

    const exact = TITLES.find(([to]) => to === pathname);
    if (exact) return exact[1];

    // Detail routes like /app/purchase/orders/3 keep their list page's title,
    // matching how the Blade detail pages set the same @section('title').
    const prefixed = TITLES
        .filter(([to]) => to !== '/app' && pathname.startsWith(`${to}/`))
        .sort((a, b) => b[0].length - a[0].length)[0];

    return prefixed ? prefixed[1] : 'Dashboard';
}
