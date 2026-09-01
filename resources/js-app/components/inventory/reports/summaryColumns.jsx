const num = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** The Blade report's inline LOW pill. */
export const LowBadge = () => (
    <span className="ml-2 px-1.5 py-0.5 text-xs font-bold rounded bg-red-200 text-red-800">LOW</span>
);

/**
 * Columns for the stock summary, reproducing the Blade report: a below-minimum
 * line is painted red end to end and its name carries a LOW badge.
 */
export const SUMMARY_COLUMNS = [
    {
        key: 'item_code',
        label: 'Item Code',
        cellClassName: (row) => `font-mono ${row.is_low ? 'text-red-700' : 'text-gray-700'}`,
    },
    {
        key: 'item_name',
        label: 'Item Name',
        cellClassName: (row) => `font-medium ${row.is_low ? 'text-red-800' : 'text-gray-800'}`,
        render: (row) => (
            <>
                {row.item_name}
                {row.is_low && <LowBadge />}
            </>
        ),
    },
    { key: 'warehouse_name', label: 'Warehouse' },
    {
        key: 'quantity',
        label: 'Quantity',
        align: 'right',
        cellClassName: (row) => `font-semibold ${row.is_low ? 'text-red-700' : 'text-gray-800'}`,
        render: (row) => num(row.quantity),
    },
    {
        key: 'minimum_stock_level',
        label: 'Min Stock',
        align: 'right',
        cellClassName: () => 'text-gray-500',
        render: (row) => num(row.minimum_stock_level),
    },
];

export const summaryRowClassName = (row) => (row.is_low ? 'bg-red-50' : undefined);

export { num };
