import { Link } from 'react-router-dom';
import Icon from '../../components/mobile/icons';
import { Card, Hero, IconTile, ListRow, MobilePage, SectionLabel } from '../../components/mobile/ui';
import { C, ZONES } from '../../components/mobile/theme';
import useOverview from '../../components/mobile/useOverview';
import { useAccess } from '../../layouts/AccessContext';
import { useShellUser } from '../../layouts/ShellUserContext';
import { visibleGroups } from '../../layouts/navItems';

// More, from SteelERP-Mobile-Designs-V2: who is signed in, then every page the
// bottom bar has no tab for, grouped as the sidebar groups them, then sign-out.
//
// The entries come from the same filtered menu as the desktop sidebar, so a
// page this person cannot open is not listed here either.

// What each menu entry looks like here, by path. An entry with no row here
// still appears (under its own label, with a plain icon) — a page added to
// navItems.js must never be unreachable on a phone.
const ROWS = {
    '/app/purchase/suppliers': { label: 'Suppliers', icon: 'building', tone: 'amber', count: 'suppliers' },
    '/app/purchase/invoices': { label: 'Supplier invoices', icon: 'fileText', tone: 'pink', count: 'unpaid_invoices' },
    '/app/purchase/payments': { label: 'Supplier payments', icon: 'cash', tone: 'pink' },
    '/app/inventory/warehouses': { label: 'Warehouses', icon: 'warehouse', tone: 'green', count: 'warehouses' },
    '/app/inventory/movements': { label: 'Stock movements & report', icon: 'swap', tone: 'green' },
    '/app/inventory/production': { label: 'Production & recipes', icon: 'production', tone: 'green' },
    '/app/inventory/reports/low-stock': { label: 'Low stock alert', icon: 'warning', tone: 'green', count: 'low_stock' },
    '/app/inventory/reports/valuation': { label: 'Valuation', icon: 'bars', tone: 'green' },
};

// Covered by a bottom tab, or folded into another row, so not repeated here.
const ON_A_TAB = new Set([
    '/app/purchase/pipeline', '/app/purchase/orders', '/app/purchase/grns',
    '/app/inventory/items', '/app/inventory/finished-goods',
    // The movement report is reached from the Stock movements page.
    '/app/inventory/reports/movement',
]);

const SECTION_ZONE = { Purchase: 'purchase', Inventory: 'inventory', System: 'system' };
const SECTION_COLOR = { Purchase: '#B45309', Inventory: '#15803D', System: '#6D28D9' };

function countFor(row, overview) {
    if (!row?.count || !overview) return null;
    const value = row.count === 'low_stock' ? overview.low_stock : overview.counts?.[row.count];

    return value === null || value === undefined ? null : Number(value).toLocaleString();
}

export default function MorePage() {
    const { isAdmin, can } = useAccess();
    const { userName, userEmail, userRole, logoutUrl, csrfToken } = useShellUser();
    const overview = useOverview();

    const groups = visibleGroups({ isAdmin, can })
        .map((group) => ({ ...group, items: group.items.filter((item) => !ON_A_TAB.has(item.to)) }))
        .filter((group) => group.items.length > 0);

    return (
        <MobilePage gap={20}>
            <Hero zone="home" variant="root" title="More">
                <Link
                    to="/app/profile"
                    style={{
                        background: C.card, borderRadius: 20, padding: 16, display: 'flex', alignItems: 'center',
                        gap: 14, textDecoration: 'none', color: C.text,
                    }}
                >
                    <span style={{
                        width: 52, height: 52, borderRadius: 26, background: C.accent, color: '#FFFFFF',
                        fontSize: 20, fontWeight: 600, display: 'flex', alignItems: 'center', justifyContent: 'center',
                        flexShrink: 0,
                    }}>
                        {(userName || 'U').charAt(0).toUpperCase()}
                    </span>
                    <span style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column', gap: 2 }}>
                        <span style={{ fontSize: 17, fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                            {userName}
                        </span>
                        <span style={{ fontSize: 14, color: C.muted, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                            {userEmail}
                        </span>
                    </span>
                    {userRole && (
                        <span style={{
                            fontSize: 12, fontWeight: 600, padding: '4px 10px', borderRadius: 999,
                            background: ZONES.home.soft, color: ZONES.home.softText, flexShrink: 0,
                        }}>
                            {userRole}
                        </span>
                    )}
                </Link>
            </Hero>

            {groups.map((group) => (
                <section key={group.label} style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                    <SectionLabel color={SECTION_COLOR[group.label]} zone={SECTION_ZONE[group.label]}>
                        {group.label}
                    </SectionLabel>
                    {group.label === 'System'
                        ? <SystemGrid items={group.items} />
                        : (
                            <Card>
                                {group.items.map((item, i) => {
                                    const row = ROWS[item.to];
                                    const count = countFor(row, overview);

                                    return (
                                        <ListRow
                                            key={item.to}
                                            to={item.to}
                                            leading={<IconTile icon={row?.icon ?? 'grid'} tone={row?.tone ?? 'slate'} size={36} radius={10} iconSize={19} />}
                                            title={<span style={{ fontWeight: 400, fontSize: 16 }}>{row?.label ?? item.label}</span>}
                                            trailing={count !== null && <span style={{ fontSize: 15, color: C.faint }}>{count}</span>}
                                            last={i === group.items.length - 1}
                                        />
                                    );
                                })}
                            </Card>
                        )}
                </section>
            ))}

            <form method="POST" action={logoutUrl || '/logout'}>
                <input type="hidden" name="_token" value={csrfToken || ''} />
                <button
                    type="submit"
                    style={{
                        width: '100%', background: C.card, borderRadius: 18, minHeight: 52, border: 0,
                        display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8,
                        color: C.danger, font: 'inherit', fontSize: 16, fontWeight: 600, cursor: 'pointer',
                    }}
                >
                    <Icon name="logout" size={20} />
                    Sign out
                </button>
            </form>
        </MobilePage>
    );
}

/** System's pages as a two-column grid of plain links, as the design draws them. */
function SystemGrid({ items }) {
    return (
        <div style={{ background: C.card, borderRadius: 18, display: 'grid', gridTemplateColumns: 'repeat(2, minmax(0, 1fr))', overflow: 'hidden' }}>
            {items.map((item, i) => {
                const lastRow = i >= items.length - (items.length % 2 === 0 ? 2 : 1);

                return (
                    <Link
                        key={item.to}
                        to={item.to}
                        style={{
                            display: 'flex', alignItems: 'center', minHeight: 52, padding: '0 16px',
                            textDecoration: 'none', color: C.text, fontSize: 15,
                            borderBottom: lastRow ? 0 : `1px solid ${C.hairline}`,
                            borderRight: i % 2 === 0 ? `1px solid ${C.hairline}` : 0,
                        }}
                    >
                        {systemLabel(item.label)}
                    </Link>
                );
            })}
        </div>
    );
}

// The sidebar's title case reads as shouting in a grid of sentence-case links.
function systemLabel(label) {
    return label.charAt(0) + label.slice(1).toLowerCase();
}
