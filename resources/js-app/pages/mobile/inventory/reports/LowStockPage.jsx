import MobileReport from '../../../../components/inventory/reports/MobileReport';
import useReport from '../../../../components/inventory/reports/useReport';
import { LowStockBanner, num } from '../../../../components/inventory/reports/lowStockColumns';
import { categoryLabel } from '../../../../components/inventory/item/itemStyles';

export default function LowStockPage() {
    const { rows, meta, loading } = useReport('/inventory/reports/low-stock', {
        errorMessage: 'Failed to load the low stock report.',
    });

    return (
        <MobileReport
            title="Low Stock Report"
            subtitle="Items currently below their minimum stock level"
            rows={rows}
            loading={loading}
            searchKeys={['item_code', 'item_name', 'warehouse_name']}
            emptyMessage="All items are above minimum stock levels."
            emptyTone="#16a34a"
            // Every row here is below minimum, so every card is tinted.
            cardClassName={() => 'bg-red-50'}
            renderCard={(row) => (
                <>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontWeight: 600, color: '#991b1b' }}>{row.item_name}</div>
                            <div className="font-mono" style={{ fontSize: 11, color: '#b91c1c' }}>{row.item_code}</div>
                        </div>
                        <span className="badge-red" style={{ flexShrink: 0 }}>LOW STOCK</span>
                    </div>

                    <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                        {categoryLabel(row.category)} · {row.warehouse_name}
                    </div>

                    <div style={{ display: 'flex', gap: 16, marginTop: 8, fontSize: 12 }}>
                        <div>
                            <div style={{ color: '#94a3b8' }}>Current</div>
                            <div style={{ fontWeight: 700, color: '#b91c1c' }}>{num(row.quantity)}</div>
                        </div>
                        <div>
                            <div style={{ color: '#94a3b8' }}>Min</div>
                            <div style={{ fontWeight: 600, color: '#4b5563' }}>{num(row.minimum_stock_level)}</div>
                        </div>
                        <div>
                            <div style={{ color: '#94a3b8' }}>Shortage</div>
                            <div style={{ fontWeight: 700, color: '#dc2626' }}>{num(row.shortfall)}</div>
                        </div>
                    </div>
                </>
            )}
        >
            <LowStockBanner count={meta.below_minimum ?? rows.length} />
        </MobileReport>
    );
}
