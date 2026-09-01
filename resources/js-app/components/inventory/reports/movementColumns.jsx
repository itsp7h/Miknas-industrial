import { formatDate, num, typeBadgeClass, typeLabel } from '../movement/movementStyles';

/**
 * Columns for the movement report, matching the Blade version: the item code in
 * mono beside its name, an IN / OUT badge, a right-aligned quantity and a
 * `d M Y` date. Notes is kept from the React version — real data the Blade
 * report did not surface.
 */
export const MOVEMENT_COLUMNS = [
    {
        key: 'item_name',
        label: 'Item',
        cellClassName: () => 'text-gray-800',
        render: (row) => (
            <>
                <span className="font-mono text-xs text-gray-500">{row.item_code ?? ''}</span>
                <span className="ml-1">{row.item_name ?? ''}</span>
            </>
        ),
    },
    { key: 'warehouse_name', label: 'Warehouse', cellClassName: () => 'text-gray-700' },
    {
        key: 'type',
        label: 'Type',
        render: (row) => <span className={typeBadgeClass(row.type)}>{typeLabel(row.type)}</span>,
    },
    {
        key: 'quantity',
        label: 'Quantity',
        align: 'right',
        cellClassName: () => 'font-medium text-gray-800',
        render: (row) => num(row.quantity),
    },
    { key: 'notes', label: 'Notes', cellClassName: () => 'text-gray-500 text-xs', render: (row) => row.notes || '—' },
    { key: 'created_at', label: 'Date', render: (row) => formatDate(row.created_at) },
];
