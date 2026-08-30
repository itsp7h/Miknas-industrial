import DesktopReport from '../../../../components/inventory/reports/DesktopReport';
import useReport from '../../../../components/inventory/reports/useReport';

const COLUMNS = [
    { key: 'item_code', label: 'Code' },
    { key: 'item_name', label: 'Item' },
    { key: 'warehouse_name', label: 'Warehouse' },
    { key: 'quantity', label: 'On Hand' },
    { key: 'minimum_stock_level', label: 'Minimum' },
    {
        key: 'shortfall',
        label: 'Shortfall',
        render: (row) => <span style={{ color: '#dc2626', fontWeight: 600 }}>{row.shortfall}</span>,
    },
];

export default function LowStockPage() {
    const { rows, meta, loading } = useReport('/inventory/reports/low-stock', {
        errorMessage: 'Failed to load the low stock report.',
    });

    return (
        <DesktopReport
            title="Low Stock Alert"
            summary={[{ label: 'Below minimum', value: meta.below_minimum ?? rows.length, tone: rows.length ? '#dc2626' : '#16a34a' }]}
            columns={COLUMNS}
            rows={rows}
            loading={loading}
            emptyMessage="Every item is at or above its minimum stock level."
        />
    );
}
