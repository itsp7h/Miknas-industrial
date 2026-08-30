import MobileReport from '../../../../components/inventory/reports/MobileReport';
import useReport from '../../../../components/inventory/reports/useReport';

const money = (value) => Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const SEARCH_KEYS = ['item_code', 'item_name', 'warehouse_name'];

export default function ValuationPage() {
    const { rows, meta, loading } = useReport('/inventory/reports/valuation', {
        errorMessage: 'Failed to load the valuation report.',
    });

    return (
        <MobileReport
            title="Stock Valuation"
            summary={[
                { label: 'Stock lines', value: rows.length },
                { label: 'Total value', value: money(meta.total_valuation) },
            ]}
            rows={rows}
            loading={loading}
            searchKeys={SEARCH_KEYS}
            emptyMessage="Nothing to value — no stock on hand."
            renderCard={(row) => (
                <>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <span style={{ fontWeight: 600 }}>{row.item_name}</span>
                        <span style={{ fontWeight: 700 }}>{money(row.valuation)}</span>
                    </div>
                    <div style={{ fontSize: 13, color: '#64748b' }}>{row.warehouse_name}</div>
                    <div style={{ fontSize: 12, color: '#94a3b8' }}>
                        {row.quantity} × {money(row.cost_price)}
                    </div>
                </>
            )}
        />
    );
}
