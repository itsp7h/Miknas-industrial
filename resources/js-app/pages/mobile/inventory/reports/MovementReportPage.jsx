import MobileReport from '../../../../components/inventory/reports/MobileReport';
import MovementFilters from '../../../../components/inventory/reports/MovementFilters';
import useReport from '../../../../components/inventory/reports/useReport';
import { TYPE_LABELS } from '../../../../components/inventory/movement/StockMovementForm';

const TYPE_COLOURS = { in: '#16a34a', out: '#dc2626', adjustment: '#ca8a04' };
const SEARCH_KEYS = ['item_name', 'item_code', 'warehouse_name', 'notes'];
const formatDate = (iso) => (iso ? new Date(iso).toLocaleDateString(undefined, { month: 'short', day: '2-digit', year: 'numeric' }) : '—');

export default function MovementReportPage() {
    const { rows, meta, loading, reload } = useReport('/inventory/reports/movement', {
        errorMessage: 'Failed to load the movement report.',
    });

    return (
        <MobileReport
            title="Movement Report"
            summary={[{ label: 'Movements', value: rows.length }]}
            rows={rows}
            loading={loading}
            searchKeys={SEARCH_KEYS}
            emptyMessage="No movements match those filters."
            renderCard={(row) => (
                <>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <span style={{ fontWeight: 600 }}>{row.item_name}</span>
                        <span style={{ color: TYPE_COLOURS[row.type], fontWeight: 600, fontSize: 13 }}>
                            {TYPE_LABELS[row.type] ?? row.type} {row.quantity}
                        </span>
                    </div>
                    <div style={{ fontSize: 13, color: '#64748b' }}>{row.warehouse_name}</div>
                    <div style={{ fontSize: 12, color: '#94a3b8' }}>{formatDate(row.created_at)}</div>
                    {row.notes && <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>{row.notes}</div>}
                </>
            )}
        >
            <MovementFilters items={meta.items ?? []} onApply={reload} />
        </MobileReport>
    );
}
