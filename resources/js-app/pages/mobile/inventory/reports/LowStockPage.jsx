import MobileReport from '../../../../components/inventory/reports/MobileReport';
import useReport from '../../../../components/inventory/reports/useReport';

const SEARCH_KEYS = ['item_code', 'item_name', 'warehouse_name'];

export default function LowStockPage() {
    const { rows, meta, loading } = useReport('/inventory/reports/low-stock', {
        errorMessage: 'Failed to load the low stock report.',
    });

    return (
        <MobileReport
            title="Low Stock Alert"
            summary={[{ label: 'Below minimum', value: meta.below_minimum ?? rows.length, tone: rows.length ? '#dc2626' : '#16a34a' }]}
            rows={rows}
            loading={loading}
            searchKeys={SEARCH_KEYS}
            emptyMessage="Every item is at or above its minimum stock level."
            renderCard={(row) => (
                <>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <span style={{ fontWeight: 600 }}>{row.item_name}</span>
                        <span style={{ color: '#dc2626', fontWeight: 700 }}>-{row.shortfall}</span>
                    </div>
                    <div style={{ fontSize: 13, color: '#64748b' }}>{row.warehouse_name}</div>
                    <div style={{ fontSize: 12, color: '#94a3b8' }}>
                        On hand {row.quantity} · minimum {row.minimum_stock_level}
                    </div>
                </>
            )}
        />
    );
}
