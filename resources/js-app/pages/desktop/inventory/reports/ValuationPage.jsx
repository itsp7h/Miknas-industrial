import DesktopReport from '../../../../components/inventory/reports/DesktopReport';
import useReport from '../../../../components/inventory/reports/useReport';

const money = (value) => Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const COLUMNS = [
    { key: 'item_code', label: 'Code' },
    { key: 'item_name', label: 'Item' },
    { key: 'warehouse_name', label: 'Warehouse' },
    { key: 'quantity', label: 'Quantity' },
    { key: 'cost_price', label: 'Cost', render: (row) => money(row.cost_price) },
    { key: 'valuation', label: 'Value', render: (row) => <strong>{money(row.valuation)}</strong> },
];

export default function ValuationPage() {
    const { rows, meta, loading } = useReport('/inventory/reports/valuation', {
        errorMessage: 'Failed to load the valuation report.',
    });

    return (
        <DesktopReport
            title="Stock Valuation"
            summary={[
                { label: 'Stock lines', value: rows.length },
                { label: 'Total value', value: money(meta.total_valuation) },
            ]}
            columns={COLUMNS}
            rows={rows}
            loading={loading}
            emptyMessage="Nothing to value — no stock on hand."
        />
    );
}
