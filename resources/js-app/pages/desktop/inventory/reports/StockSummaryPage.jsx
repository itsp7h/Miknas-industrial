import DesktopReport from '../../../../components/inventory/reports/DesktopReport';
import useReport from '../../../../components/inventory/reports/useReport';
import { SUMMARY_COLUMNS, summaryRowClassName } from '../../../../components/inventory/reports/summaryColumns';

export default function StockSummaryPage() {
    const { rows, meta, loading } = useReport('/inventory/reports/summary', {
        errorMessage: 'Failed to load the stock summary.',
    });

    const belowMinimum = meta.below_minimum ?? 0;

    return (
        <DesktopReport
            title="Inventory Summary"
            subtitle="Current stock levels across all warehouses"
            summary={[
                { label: 'Stock lines', value: meta.total_lines ?? rows.length },
                // The report exists to surface these, so the count leads with them.
                { label: 'Below minimum', value: belowMinimum, tone: belowMinimum > 0 ? '#dc2626' : undefined },
            ]}
            columns={SUMMARY_COLUMNS}
            rows={rows}
            rowClassName={summaryRowClassName}
            loading={loading}
            emptyMessage="No stock data available."
        />
    );
}
