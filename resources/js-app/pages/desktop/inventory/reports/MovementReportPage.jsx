import DesktopReport from '../../../../components/inventory/reports/DesktopReport';
import MovementFilters from '../../../../components/inventory/reports/MovementFilters';
import useReport from '../../../../components/inventory/reports/useReport';
import { TYPE_LABELS } from '../../../../components/inventory/movement/StockMovementForm';

const TYPE_COLOURS = { in: '#16a34a', out: '#dc2626', adjustment: '#ca8a04' };

const formatDate = (iso) => (iso ? new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: '2-digit' }) : '—');

const COLUMNS = [
    { key: 'created_at', label: 'Date', render: (row) => formatDate(row.created_at) },
    { key: 'item_name', label: 'Item' },
    { key: 'warehouse_name', label: 'Warehouse' },
    {
        key: 'type',
        label: 'Type',
        render: (row) => <span style={{ color: TYPE_COLOURS[row.type], fontWeight: 600 }}>{TYPE_LABELS[row.type] ?? row.type}</span>,
    },
    { key: 'quantity', label: 'Quantity' },
    { key: 'notes', label: 'Notes', render: (row) => row.notes || '—' },
];

export default function MovementReportPage() {
    const { rows, meta, loading, reload } = useReport('/inventory/reports/movement', {
        errorMessage: 'Failed to load the movement report.',
    });

    return (
        <DesktopReport
            title="Movement Report"
            summary={[{ label: 'Movements', value: rows.length }]}
            columns={COLUMNS}
            rows={rows}
            loading={loading}
            emptyMessage="No movements match those filters."
        >
            <MovementFilters items={meta.items ?? []} onApply={reload} />
        </DesktopReport>
    );
}
