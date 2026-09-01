import {
    CurrencyIcon, BoxIcon, CogIcon, ClockIcon, ClipboardIcon,
    PlusIcon, WarningIcon,
} from './icons';

/** Matches the Blade dashboard's `number_format($value, 0)`. */
export const formatKpi = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { maximumFractionDigits: 0 });

// The five KPI cards, in the Blade page's order. `to` makes a card a link —
// only Purchase Pipeline had one.
export const KPIS = [
    {
        key: 'total_sales',
        label: 'Total Sales',
        caption: 'All time invoiced',
        Icon: CurrencyIcon,
        iconWrap: 'bg-blue-100',
        iconColor: 'text-blue-600',
    },
    {
        key: 'inventory_value',
        label: 'Inventory Value',
        caption: 'All warehouses',
        Icon: BoxIcon,
        iconWrap: 'bg-emerald-100',
        iconColor: 'text-emerald-600',
    },
    {
        key: 'production_in_progress',
        label: 'Production Active',
        caption: 'Orders in progress',
        Icon: CogIcon,
        iconWrap: 'bg-orange-100',
        iconColor: 'text-orange-600',
    },
    {
        key: 'purchase_pending',
        label: 'Purchase Pipeline',
        caption: 'Active pipelines',
        Icon: ClockIcon,
        iconWrap: 'bg-amber-100 group-hover:bg-amber-200 transition-colors',
        iconColor: 'text-amber-600',
        to: '/app/purchase/pipeline',
        hover: 'hover:border-amber-300 hover:shadow-md transition-all duration-200 group',
    },
    {
        key: 'outstanding_receivables',
        label: 'Receivables',
        caption: 'Outstanding',
        Icon: ClipboardIcon,
        iconWrap: 'bg-red-100',
        iconColor: 'text-red-600',
        // The only KPI whose figure is not slate-800.
        valueColor: 'text-red-600',
    },
];

export const QUICK_ACTIONS = [
    {
        label: 'New Purchase Request',
        caption: 'Raise a PR for approval',
        // Still Blade — a real navigation, not a router link.
        to: '/purchase/requests/create',
        Icon: PlusIcon,
        border: 'hover:border-amber-300',
        iconWrap: 'bg-amber-50 group-hover:bg-amber-100',
        iconColor: 'text-amber-600',
        labelHover: 'group-hover:text-amber-700',
    },
    {
        label: 'New Sales Order',
        caption: 'Create order for a customer',
        to: '/app/sales/orders',
        Icon: PlusIcon,
        border: 'hover:border-violet-300',
        iconWrap: 'bg-violet-50 group-hover:bg-violet-100',
        iconColor: 'text-violet-600',
        labelHover: 'group-hover:text-violet-700',
    },
    {
        label: 'New Production Order',
        caption: 'Schedule a production run',
        to: '/app/production/orders',
        Icon: PlusIcon,
        border: 'hover:border-orange-300',
        iconWrap: 'bg-orange-50 group-hover:bg-orange-100',
        iconColor: 'text-orange-600',
        labelHover: 'group-hover:text-orange-700',
    },
    {
        label: 'Low Stock Alert',
        caption: 'Items below minimum level',
        to: '/app/inventory/reports/low-stock',
        Icon: WarningIcon,
        border: 'hover:border-red-300',
        iconWrap: 'bg-red-50 group-hover:bg-red-100',
        iconColor: 'text-red-500',
        labelHover: 'group-hover:text-red-600',
    },
];

export const MODULES = [
    {
        title: 'Purchase',
        caption: 'Procurement workflow',
        header: 'bg-gradient-to-r from-amber-500 to-amber-400',
        captionColor: 'text-amber-100',
        linkHover: 'hover:text-amber-600',
        links: [
            { label: 'Purchase Requests', to: '/purchase/requests' },
            { label: 'Purchase Orders', to: '/app/purchase/orders' },
            { label: 'Goods Receipt (GRN)', to: '/app/purchase/grns' },
        ],
    },
    {
        title: 'Inventory',
        caption: 'Stock management',
        header: 'bg-gradient-to-r from-emerald-500 to-emerald-400',
        captionColor: 'text-emerald-100',
        linkHover: 'hover:text-emerald-600',
        links: [
            { label: 'Item Master', to: '/app/inventory/items' },
            { label: 'Stock Summary', to: '/app/inventory/reports/summary' },
            { label: 'Inventory Valuation', to: '/app/inventory/reports/valuation' },
        ],
    },
    {
        title: 'Production',
        caption: 'Manufacturing operations',
        header: 'bg-gradient-to-r from-orange-500 to-orange-400',
        captionColor: 'text-orange-100',
        linkHover: 'hover:text-orange-600',
        links: [
            { label: 'Production Orders', to: '/app/production/orders' },
            { label: 'Bill of Materials', to: '/app/production/bom' },
            { label: 'Material Issues', to: '/app/production/material-issues' },
        ],
    },
    {
        title: 'Sales',
        caption: 'Customer & revenue',
        header: 'bg-gradient-to-r from-violet-500 to-violet-400',
        captionColor: 'text-violet-100',
        linkHover: 'hover:text-violet-600',
        links: [
            { label: 'Sales Orders', to: '/app/sales/orders' },
            { label: 'Sales Invoices', to: '/app/sales/invoices' },
            { label: 'Customers', to: '/app/sales/customers' },
        ],
    },
];
