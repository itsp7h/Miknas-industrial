import MobileReport from '../../../../components/inventory/reports/MobileReport';
import useReport from '../../../../components/inventory/reports/useReport';
import { LowBadge, num } from '../../../../components/inventory/reports/summaryColumns';

export default function StockSummaryPage() {
    const { rows, meta, loading } = useReport('/inventory/reports/summary', {
        errorMessage: 'Failed to load the stock summary.',
    });

    const belowMinimum = meta.below_minimum ?? 0;

    return (
        <MobileReport
            title="Inventory Summary"
            subtitle="Current stock levels across all warehouses"
            summary={[
                { label: 'Stock lines', value: meta.total_lines ?? rows.length },
                { label: 'Below minimum', value: belowMinimum, tone: belowMinimum > 0 ? '#dc2626' : undefined },
            ]}
            rows={rows}
            loading={loading}
            searchKeys={['item_code', 'item_name', 'warehouse_name']}
            emptyMessage="No stock data available."
            // A below-minimum line is tinted red here too, since the card
            // replaces the table row.
            cardClassName={(row) => (row.is_low ? 'bg-red-50' : undefined)}
            renderCard={(row) => (
                <>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontWeight: 600, color: row.is_low ? '#991b1b' : '#0f172a' }}>
                                {row.item_name}
                                {row.is_low && <LowBadge />}
                            </div>
                            <div className="font-mono" style={{ fontSize: 11, color: row.is_low ? '#b91c1c' : '#94a3b8' }}>
                                {row.item_code}
                            </div>
                        </div>
                        <div style={{ textAlign: 'right', flexShrink: 0 }}>
                            <div style={{ fontWeight: 700, color: row.is_low ? '#b91c1c' : '#1f2937' }}>
                                {num(row.quantity)}
                            </div>
                            <div style={{ fontSize: 11, color: '#94a3b8' }}>min {num(row.minimum_stock_level)}</div>
                        </div>
                    </div>
                    <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>{row.warehouse_name}</div>
                </>
            )}
        />
    );
}
