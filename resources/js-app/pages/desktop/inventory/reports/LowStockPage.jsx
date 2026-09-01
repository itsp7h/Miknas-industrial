import DesktopReport from '../../../../components/inventory/reports/DesktopReport';
import useReport from '../../../../components/inventory/reports/useReport';
import {
    LOW_STOCK_COLUMNS, LowStockBanner, lowStockRowClassName,
} from '../../../../components/inventory/reports/lowStockColumns';

export default function LowStockPage() {
    const { rows, meta, loading } = useReport('/inventory/reports/low-stock', {
        errorMessage: 'Failed to load the low stock report.',
    });

    return (
        <DesktopReport
            title="Low Stock Report"
            subtitle="Items currently below their minimum stock level"
            columns={LOW_STOCK_COLUMNS}
            rows={rows}
            noun="items"
            rowClassName={lowStockRowClassName}
            loading={loading}
            emptyMessage="All items are above minimum stock levels."
            emptyTone="#16a34a"
        >
            {/* The banner carries the count, so no separate summary strip. */}
            <LowStockBanner count={meta.below_minimum ?? rows.length} />
        </DesktopReport>
    );
}
