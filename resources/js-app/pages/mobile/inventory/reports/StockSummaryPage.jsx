import MobileReport from '../../../../components/inventory/reports/MobileReport';
import useReport from '../../../../components/inventory/reports/useReport';

const SEARCH_KEYS = ['item_code', 'item_name', 'warehouse_name'];

export default function StockSummaryPage() {
    const { rows, meta, loading } = useReport('/inventory/reports/summary', {
        errorMessage: 'Failed to load the stock summary.',
    });

    return (
        <MobileReport
            title="Stock Summary"
            summary={[{ label: 'Stock lines', value: meta.total_lines ?? rows.length }]}
            rows={rows}
            loading={loading}
            searchKeys={SEARCH_KEYS}
            emptyMessage="No stock on hand yet."
            renderCard={(row) => (
                <>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <span style={{ fontWeight: 600 }}>{row.item_name}</span>
                        <span style={{ fontWeight: 700 }}>{row.quantity} {row.unit_of_measure}</span>
                    </div>
                    <div style={{ fontSize: 13, color: '#64748b' }}>{row.warehouse_name}</div>
                    <div style={{ fontSize: 12, color: '#94a3b8' }}>{row.item_code}</div>
                </>
            )}
        />
    );
}
