import { currencySymbol } from '../../currency';
import {
    CurrencyIcon, BoxIcon, CogIcon, ClockIcon, ClipboardIcon,
    PlusIcon, WarningIcon,
} from './icons';

// `hidden: true` parks an entry without deleting it — same flag, same reason as
// the Production and Sales groups in layouts/navItems.js: the modules are built
// and still reachable by URL, they are just not in use yet. Delete the flag to
// bring an entry back, and the grids below re-widen on their own.
const visible = (entries) => entries.filter((entry) => !entry.hidden);

// Tailwind JIT only compiles class strings it can see spelled out in source
// (CLAUDE.md #1), so the widths each grid can take are listed here rather than
// interpolated from an array length — `lg:grid-cols-${n}` would never reach the
// compiled CSS, and the dashboard would silently fall back to one column.
const XL_COLS = { 1: 'xl:grid-cols-1', 2: 'xl:grid-cols-2', 3: 'xl:grid-cols-3', 4: 'xl:grid-cols-4', 5: 'xl:grid-cols-5' };
const LG_COLS = { 1: 'lg:grid-cols-1', 2: 'lg:grid-cols-2', 3: 'lg:grid-cols-3', 4: 'lg:grid-cols-4' };

/**
 * Matches the Blade dashboard's `number_format($value, 0)`.
 *
 * Three of the five cards are amounts and two are counts of orders, so the
 * card says which it is rather than every figure getting a currency.
 * Whole units on purpose: a headline reads better as BD 25,651 than to the fils.
 */
export const formatKpi = (value, isMoney = false) => {
    const figure = Number(value ?? 0).toLocaleString(undefined, { maximumFractionDigits: 0 });

    return isMoney ? `${currencySymbol()} ${figure}` : figure;
};

// The five KPI cards, in the Blade page's order. `to` makes a card a link —
// only Purchase Pipeline had one.
const ALL_KPIS = [
    {
        key: 'total_sales',
        money: true,
        label: 'Total Sales',
        caption: 'All time invoiced',
        Icon: CurrencyIcon,
        iconWrap: 'bg-blue-100',
        iconColor: 'text-blue-600',
    },
    {
        key: 'inventory_value',
        money: true,
        label: 'Inventory Value',
        caption: 'All warehouses',
        Icon: BoxIcon,
        iconWrap: 'bg-emerald-100',
        iconColor: 'text-emerald-600',
    },
    {
        key: 'production_in_progress',
        hidden: true,
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
        money: true,
        label: 'Receivables',
        caption: 'Outstanding',
        Icon: ClipboardIcon,
        iconWrap: 'bg-red-100',
        iconColor: 'text-red-600',
        // The only KPI whose figure is not slate-800.
        valueColor: 'text-red-600',
    },
];

export const KPIS = visible(ALL_KPIS);
export const KPI_COLUMNS = XL_COLS[KPIS.length];

const ALL_QUICK_ACTIONS = [
    {
        label: 'New Purchase Request',
        caption: 'Raise a PR for approval',
        // The MPR form is a modal, not a page: the board opens it on ?new=1,
        // which is also where the old /purchase/requests/create URL redirects.
        to: '/app/purchase/pipeline?new=1',
        Icon: PlusIcon,
        border: 'hover:border-amber-300',
        iconWrap: 'bg-amber-50 group-hover:bg-amber-100',
        iconColor: 'text-amber-600',
        labelHover: 'group-hover:text-amber-700',
    },
    {
        hidden: true,
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
        hidden: true,
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

export const QUICK_ACTIONS = visible(ALL_QUICK_ACTIONS);
export const QUICK_ACTION_COLUMNS = LG_COLS[QUICK_ACTIONS.length];

const ALL_MODULES = [
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
            { label: 'Raw Materials', to: '/app/inventory/items' },
            { label: 'Inventory Valuation', to: '/app/inventory/reports/valuation' },
        ],
    },
    {
        title: 'Production',
        hidden: true,
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
        hidden: true,
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

export const MODULES = visible(ALL_MODULES);
export const MODULE_COLUMNS = LG_COLS[MODULES.length];
