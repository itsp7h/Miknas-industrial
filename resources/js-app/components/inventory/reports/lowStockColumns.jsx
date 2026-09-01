import { categoryLabel } from '../item/itemStyles';

const num = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** Blade's red banner above the table — it named the count and the urgency. */
export function LowStockBanner({ count }) {
    if (!count) return null;

    return (
        <div className="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 font-medium">
            {count} item(s) are below minimum stock level. Immediate action may be required.
        </div>
    );
}

/**
 * Every row on this report is by definition below its minimum, so Blade tinted
 * them all red and badged each LOW STOCK — there is no "healthy" row to
 * contrast against.
 */
export const LOW_STOCK_COLUMNS = [
    { key: 'item_code', label: 'Item Code', cellClassName: () => 'font-mono text-red-700' },
    { key: 'item_name', label: 'Item Name', cellClassName: () => 'font-medium text-red-800' },
    {
        key: 'category',
        label: 'Category',
        cellClassName: () => 'text-gray-600',
        render: (row) => categoryLabel(row.category),
    },
    { key: 'warehouse_name', label: 'Warehouse', cellClassName: () => 'text-gray-600' },
    {
        key: 'quantity',
        label: 'Current Qty',
        align: 'right',
        cellClassName: () => 'font-bold text-red-700',
        render: (row) => num(row.quantity),
    },
    {
        key: 'minimum_stock_level',
        label: 'Min Stock',
        align: 'right',
        cellClassName: () => 'text-gray-600',
        render: (row) => num(row.minimum_stock_level),
    },
    {
        key: 'shortfall',
        label: 'Shortage',
        align: 'right',
        cellClassName: () => 'font-semibold text-red-600',
        render: (row) => num(row.shortfall),
    },
    {
        key: 'status',
        label: 'Status',
        render: () => <span className="badge-red">LOW STOCK</span>,
    },
];

export const lowStockRowClassName = () => 'bg-red-50';

export { num };
