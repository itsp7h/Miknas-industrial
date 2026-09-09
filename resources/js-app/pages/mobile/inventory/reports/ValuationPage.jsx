import MobileReport from '../../../../components/inventory/reports/MobileReport';
import useReport from '../../../../components/inventory/reports/useReport';
import { num } from '../../../../components/inventory/reports/valuationColumns';
import { categoryBadgeClass, categoryLabel } from '../../../../components/inventory/item/itemStyles';

export default function ValuationPage() {
    const { rows, meta, loading } = useReport('/inventory/reports/valuation', {
        errorMessage: 'Failed to load the valuation report.',
    });

    return (
        <MobileReport
            title="Inventory Valuation"
            subtitle="Total value of current stock"
            // A card list has no footer row, so the grand total leads instead.
            summary={[{ label: 'Grand total', value: num(meta.total_valuation), tone: '#1d4ed8' }]}
            rows={rows}
            loading={loading}
            searchKeys={['item_code', 'item_name']}
            emptyMessage="No valuation data available."
            renderCard={(row) => (
                <>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontWeight: 600, color: '#0f172a' }}>{row.item_name}</div>
                            <div className="font-mono" style={{ fontSize: 11, color: '#94a3b8' }}>{row.item_code}</div>
                        </div>
                        <div style={{ fontWeight: 700, color: '#1d4ed8', flexShrink: 0 }}>{num(row.total_value)}</div>
                    </div>

                    <div style={{ marginTop: 6 }}>
                        <span className={categoryBadgeClass(row.category)}>{categoryLabel(row.category)}</span>
                    </div>

                    <div style={{ fontSize: 12, color: '#64748b', marginTop: 6 }}>
                        {num(row.total_qty)} × {num(row.cost_price)}
                    </div>
                </>
            )}
        />
    );
}
