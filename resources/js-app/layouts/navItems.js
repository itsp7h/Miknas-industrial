// Shared nav structure for DesktopShell and MobileShell.
//
// `type: 'link'` items are React-Router-owned routes (rendered with <Link>,
// client-side navigation, no full page load).
// `type: 'href'` items are still Blade pages — they MUST use a plain <a> so
// the browser does a real navigation; React Router doesn't own these URLs.
//
// Mirrors the top-level sidebar links in resources/views/layouts/app.blade.php.

export const DASHBOARD_ITEM = { type: 'link', to: '/app', label: 'Dashboard' };

export const NAV_GROUPS = [
    {
        label: 'Purchase',
        items: [
            { type: 'link', to: '/app/purchase/pipeline', label: 'Pipeline' },
            { type: 'link', to: '/app/purchase/suppliers', label: 'Suppliers' },
            { type: 'href', to: '/purchase/orders', label: 'Purchase Orders' },
            { type: 'href', to: '/purchase/grns', label: 'Goods Receipt (GRN)' },
            { type: 'href', to: '/purchase/invoices', label: 'Supplier Invoices' },
            { type: 'href', to: '/purchase/payments', label: 'Payments' },
        ],
    },
    {
        label: 'Inventory',
        items: [
            { type: 'href', to: '/inventory/items', label: 'Items' },
            { type: 'href', to: '/inventory/warehouses', label: 'Warehouses' },
            { type: 'href', to: '/inventory/movements', label: 'Stock Movements' },
            { type: 'href', to: '/inventory/reports/summary', label: 'Stock Summary' },
            { type: 'href', to: '/inventory/reports/movement', label: 'Movement Report' },
            { type: 'href', to: '/inventory/reports/low-stock', label: 'Low Stock Alert' },
            { type: 'href', to: '/inventory/reports/valuation', label: 'Valuation' },
        ],
    },
    {
        label: 'Production',
        items: [
            { type: 'href', to: '/production/orders', label: 'Production Orders' },
            { type: 'href', to: '/production/bom', label: 'Bill of Materials' },
            { type: 'href', to: '/production/material-issues', label: 'Material Issues' },
            { type: 'href', to: '/production/outputs', label: 'Production Output' },
        ],
    },
    {
        label: 'Sales',
        items: [
            { type: 'href', to: '/sales/customers', label: 'Customers' },
            { type: 'href', to: '/sales/orders', label: 'Sales Orders' },
            { type: 'href', to: '/sales/delivery-notes', label: 'Delivery Notes' },
            { type: 'href', to: '/sales/invoices', label: 'Sales Invoices' },
            { type: 'href', to: '/sales/payments', label: 'Payment Receipts' },
        ],
    },
    {
        label: 'System',
        adminOnly: true,
        items: [
            { type: 'href', to: '/settings/projects', label: 'Companies' },
            { type: 'href', to: '/settings/projects-overview', label: 'Projects' },
            { type: 'href', to: '/settings/users', label: 'Users' },
            { type: 'href', to: '/settings/integrations', label: 'Integrations' },
            { type: 'href', to: '/settings/vat', label: 'VAT Settings' },
        ],
    },
];
