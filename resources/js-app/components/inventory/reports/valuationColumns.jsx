import { categoryBadgeClass, categoryLabel } from '../item/itemStyles';

const num = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** Columns as the Blade valuation report headed them. */
export const VALUATION_COLUMNS = [
    { key: 'item_code', label: 'Item Code', cellClassName: () => 'font-mono text-gray-700' },
    { key: 'item_name', label: 'Item Name', cellClassName: () => 'font-medium text-gray-800' },
    {
        key: 'category',
        label: 'Category',
        render: (row) => (
            <span className={categoryBadgeClass(row.category)}>{categoryLabel(row.category)}</span>
        ),
    },
    {
        key: 'total_qty',
        label: 'Total Qty',
        align: 'right',
        cellClassName: () => 'text-gray-700',
        render: (row) => num(row.total_qty),
    },
    {
        key: 'cost_price',
        label: 'Cost Price',
        align: 'right',
        cellClassName: () => 'text-gray-600',
        render: (row) => num(row.cost_price),
    },
    {
        key: 'total_value',
        label: 'Total Value',
        align: 'right',
        cellClassName: () => 'font-semibold text-gray-800',
        render: (row) => num(row.total_value),
    },
];

/**
 * Blade closed the table with a blue-tinted Grand Total row. It totals the rows
 * currently shown, so it stays truthful while a search narrows the table.
 */
export const valuationFooter = (rows) => (
    <tfoot className="bg-blue-50 border-t-2 border-blue-200">
        <tr>
            <td colSpan={5} className="px-4 py-3 text-right font-bold text-gray-800 text-sm">Grand Total</td>
            <td className="px-4 py-3 text-right font-bold text-blue-700 text-base">
                {num(rows.reduce((sum, row) => sum + Number(row.total_value ?? 0), 0))}
            </td>
        </tr>
    </tfoot>
);

export { num };
