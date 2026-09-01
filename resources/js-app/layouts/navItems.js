// Shared nav structure for DesktopShell and MobileShell.
//
// `type: 'link'` items are React-Router-owned routes (rendered with <Link>,
// client-side navigation, no full page load).
// `type: 'href'` items are still Blade pages — they MUST use a plain <a> so
// the browser does a real navigation; React Router doesn't own these URLs.
//
// Mirrors the top-level sidebar links in resources/views/layouts/app.blade.php,
// including each section's heading colour and icon and the Pipeline link's
// highlight, so the React sidebar renders the same thing.

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
            { type: 'link', to: '/app/purchase/pipeline', label: 'Pipeline', highlight: true },
            { type: 'link', to: '/app/purchase/suppliers', label: 'Suppliers' },
            { type: 'link', to: '/app/purchase/orders', label: 'Purchase Orders' },
            { type: 'link', to: '/app/purchase/grns', label: 'Goods Receipt (GRN)' },
            { type: 'link', to: '/app/purchase/invoices', label: 'Supplier Invoices' },
            { type: 'href', to: '/purchase/payments', label: 'Payments' },
        ],
    },
    {
        label: 'Inventory',
        color: '#10b981',
        icon: BOX,
        items: [
            { type: 'link', to: '/app/inventory/items', label: 'Items' },
            { type: 'link', to: '/app/inventory/warehouses', label: 'Warehouses' },
            { type: 'link', to: '/app/inventory/movements', label: 'Stock Movements' },
            { type: 'link', to: '/app/inventory/reports/summary', label: 'Stock Summary' },
            { type: 'link', to: '/app/inventory/reports/movement', label: 'Movement Report' },
            { type: 'link', to: '/app/inventory/reports/low-stock', label: 'Low Stock Alert' },
            { type: 'link', to: '/app/inventory/reports/valuation', label: 'Valuation' },
        ],
    },
    {
        label: 'Production',
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
        adminOnly: true,
        color: '#64748b',
        icon: COG,
        // Alone among the groups, the Blade System links carried their own 14px
        // icon beside the label.
        items: [
            { type: 'href', to: '/settings/projects', label: 'Companies', icon: BUILDING },
            { type: 'href', to: '/settings/projects-overview', label: 'Projects', icon: FOLDER },
            { type: 'href', to: '/settings/users', label: 'Users', icon: USERS },
            { type: 'href', to: '/settings/integrations', label: 'Integrations', icon: COG },
            { type: 'href', to: '/settings/vat', label: 'VAT Settings', icon: RECEIPT },
        ],
    },
];
