import MobileReport from '../../../../components/inventory/reports/MobileReport';
import MovementFilters from '../../../../components/inventory/reports/MovementFilters';
import useReport from '../../../../components/inventory/reports/useReport';
import { formatDate, num, typeBadgeClass, typeLabel } from '../../../../components/inventory/movement/movementStyles';

export default function MovementReportPage() {
    const { rows, meta, loading, reload } = useReport('/inventory/reports/movement', {
        errorMessage: 'Failed to load the movement report.',
    });

    return (
        <MobileReport
            title="Movement Report"
            subtitle="View stock movements within a date range"
            summary={[{ label: 'Movements', value: rows.length }]}
            rows={rows}
            loading={loading}
            searchKeys={['item_code', 'item_name', 'warehouse_name', 'notes']}
            emptyMessage="No movements found for selected filters."
            renderCard={(row) => (
                <>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontWeight: 600, color: '#0f172a' }}>{row.item_name ?? '—'}</div>
                            <div className="font-mono" style={{ fontSize: 11, color: '#94a3b8' }}>{row.item_code ?? ''}</div>
                        </div>
                        <div style={{ textAlign: 'right', flexShrink: 0 }}>
                            <span className={typeBadgeClass(row.type)}>{typeLabel(row.type)}</span>
                            <div style={{ fontWeight: 700, color: '#1f2937', marginTop: 4 }}>{num(row.quantity)}</div>
                        </div>
                    </div>
                    <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                        {row.warehouse_name ?? '—'} · {formatDate(row.created_at)}
                    </div>
                    {row.notes && <div style={{ fontSize: 11, color: '#94a3b8' }}>{row.notes}</div>}
                </>
            )}
        >
            <MovementFilters items={meta.items ?? []} onApply={reload} />
        </MobileReport>
    );
}
