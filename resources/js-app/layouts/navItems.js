// Shared nav structure for DesktopShell and MobileShell.
//
// `type: 'link'` items are React-Router-owned routes (rendered with <Link>,
// client-side navigation, no full page load).
// `type: 'href'` items are still Blade pages — they MUST use a plain <a> so
// the browser does a real navigation; React Router doesn't own these URLs. No
// entry needs it any more (every sidebar page is in the shell), but the shells
// still honour it, so a not-yet-migrated page can be added back at any time.
//
// Mirrors the top-level sidebar links in resources/views/layouts/app.blade.php,
// including each section's heading colour and icon and the Pipeline link's
// highlight, so the React sidebar renders the same thing.
//
// `permission` on an item is the permission that opens its tab — `<tab>.view`.
// An item without one is open to everyone (the Dashboard); an item with
// `adminOnly` has no permission name at all and is Admin's alone.
//
// `hidden: true` on a group keeps its routes and page titles but drops it from
// both menus (the desktop sidebar and the mobile drawer/bottom bar). Production
// and Sales are parked that way: the modules are built and reachable by URL,
// they are just not in use yet. Remove the flag to bring a group back.

import { BAG, BOX, BUILDING, CHART, COG, FOLDER, GRID, RECEIPT, USERS } from './navIcons';

export const DASHBOARD_ITEM = { type: 'link', to: '/app', label: 'Dashboard', icon: GRID };

export const NAV_GROUPS = [
    {
        label: 'Purchase',
        color: '#f59e0b',
        icon: BAG,
        items: [
            // The Blade sidebar singled Pipeline out: amber text, semibold, and a
            // blue active pill rather than the usual slate one.
            { type: 'link', to: '/app/purchase/pipeline', label: 'Pipeline', highlight: true, permission: 'pipeline.view' },
            { type: 'link', to: '/app/purchase/suppliers', label: 'Suppliers', permission: 'suppliers.view' },
            { type: 'link', to: '/app/purchase/orders', label: 'Purchase Orders', permission: 'purchase-orders.view' },
            { type: 'link', to: '/app/purchase/grns', label: 'Goods Receipt (GRN)', permission: 'goods-receipts.view' },
            { type: 'link', to: '/app/purchase/invoices', label: 'Supplier Invoices', permission: 'supplier-invoices.view' },
            { type: 'link', to: '/app/purchase/payments', label: 'Payments', permission: 'supplier-payments.view' },
        ],
    },
    {
        label: 'Inventory',
        color: '#10b981',
        icon: BOX,
        items: [
            { type: 'link', to: '/app/inventory/items', label: 'Raw Materials', permission: 'raw-materials.view' },
            { type: 'link', to: '/app/inventory/finished-goods', label: 'Finished Goods', permission: 'finished-goods.view' },
            { type: 'link', to: '/app/inventory/warehouses', label: 'Warehouses', permission: 'warehouses.view' },
            { type: 'link', to: '/app/inventory/movements', label: 'Stock Movements', permission: 'stock-movements.view' },
            { type: 'link', to: '/app/inventory/reports/movement', label: 'Movement Report', permission: 'movement-report.view' },
            { type: 'link', to: '/app/inventory/reports/low-stock', label: 'Low Stock Alert', permission: 'low-stock.view' },
            { type: 'link', to: '/app/inventory/reports/valuation', label: 'Valuation', permission: 'valuation.view' },
        ],
    },
    {
        label: 'Production',
        hidden: true,
        color: '#f97316',
        icon: COG,
        items: [
            { type: 'link', to: '/app/production/orders', label: 'Production Orders' },
            { type: 'link', to: '/app/production/bom', label: 'Bill of Materials' },
            { type: 'link', to: '/app/production/material-issues', label: 'Material Issues' },
            { type: 'link', to: '/app/production/outputs', label: 'Production Output' },
        ],
    },
    {
        label: 'Sales',
        hidden: true,
        color: '#a78bfa',
        icon: CHART,
        items: [
            { type: 'link', to: '/app/sales/customers', label: 'Customers' },
            { type: 'link', to: '/app/sales/orders', label: 'Sales Orders' },
            { type: 'link', to: '/app/sales/delivery-notes', label: 'Delivery Notes' },
            { type: 'link', to: '/app/sales/invoices', label: 'Sales Invoices' },
            { type: 'link', to: '/app/sales/payments', label: 'Payment Receipts' },
        ],
    },
    {
        label: 'System',
        color: '#64748b',
        icon: COG,
        // Alone among the groups, the Blade System links carried their own 14px
        // icon beside the label.
        items: [
            { type: 'link', to: '/app/settings/companies', label: 'Companies', icon: BUILDING, permission: 'companies.view' },
            { type: 'link', to: '/app/settings/projects', label: 'Projects', icon: FOLDER, permission: 'projects.view' },
            { type: 'link', to: '/app/settings/users', label: 'Users', icon: USERS, adminOnly: true },
            { type: 'link', to: '/app/settings/integrations', label: 'Integrations', icon: COG, adminOnly: true },
            { type: 'link', to: '/app/settings/finance', label: 'Finance', icon: RECEIPT, permission: 'finance.view' },
            { type: 'link', to: '/app/settings/item-categories', label: 'Item Categories', icon: BOX, permission: 'item-categories.view' },
        ],
    },
];

// What the two shells actually render. NAV_GROUPS stays complete so
// usePageTitle still titles a hidden group's pages when one is opened directly.
export const MENU_GROUPS = NAV_GROUPS.filter((group) => !group.hidden);

/**
 * The menu as one person sees it: tabs they cannot open are not shown.
 *
 * A group whose every item is filtered away goes too — an empty "Inventory"
 * heading says there is something there when there is not.
 */
export function visibleGroups({ isAdmin = false, can = () => false } = {}) {
    if (isAdmin) return MENU_GROUPS;

    return MENU_GROUPS
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => {
                if (item.adminOnly) return false;

                return item.permission ? can(item.permission) : true;
            }),
        }))
        .filter((group) => group.items.length > 0);
}
