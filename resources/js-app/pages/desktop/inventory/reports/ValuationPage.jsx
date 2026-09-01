import DesktopReport from '../../../../components/inventory/reports/DesktopReport';
import useReport from '../../../../components/inventory/reports/useReport';
import { VALUATION_COLUMNS, valuationFooter } from '../../../../components/inventory/reports/valuationColumns';

export default function ValuationPage() {
    const { rows, loading } = useReport('/inventory/reports/valuation', {
        errorMessage: 'Failed to load the valuation report.',
    });

    return (
        <DesktopReport
            title="Inventory Valuation"
            subtitle="Total value of current stock"
            columns={VALUATION_COLUMNS}
            rows={rows}
            noun="items"
            // The grand total is the table's own footer row, as in Blade, rather
            // than a separate strip above it.
            footer={valuationFooter}
            loading={loading}
            emptyMessage="No valuation data available."
        />
    );
}
