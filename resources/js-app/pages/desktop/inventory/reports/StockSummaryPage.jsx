import DesktopReport from '../../../../components/inventory/reports/DesktopReport';
import useReport from '../../../../components/inventory/reports/useReport';

const COLUMNS = [
    { key: 'item_code', label: 'Code' },
    { key: 'item_name', label: 'Item' },
    { key: 'warehouse_name', label: 'Warehouse' },
    { key: 'quantity', label: 'Quantity' },
    { key: 'unit_of_measure', label: 'Unit' },
];

export default function StockSummaryPage() {
    const { rows, meta, loading } = useReport('/inventory/reports/summary', {
        errorMessage: 'Failed to load the stock summary.',
    });

    return (
        <DesktopReport
            title="Stock Summary"
            summary={[{ label: 'Stock lines', value: meta.total_lines ?? rows.length }]}
            columns={COLUMNS}
            rows={rows}
            loading={loading}
            emptyMessage="No stock on hand yet."
        />
    );
}
