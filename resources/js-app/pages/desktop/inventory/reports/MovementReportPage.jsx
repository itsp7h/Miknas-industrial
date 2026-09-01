import DesktopReport from '../../../../components/inventory/reports/DesktopReport';
import MovementFilters from '../../../../components/inventory/reports/MovementFilters';
import useReport from '../../../../components/inventory/reports/useReport';
import { MOVEMENT_COLUMNS } from '../../../../components/inventory/reports/movementColumns';

export default function MovementReportPage() {
    const { rows, meta, loading, reload } = useReport('/inventory/reports/movement', {
        errorMessage: 'Failed to load the movement report.',
    });

    return (
        <DesktopReport
            title="Movement Report"
            subtitle="View stock movements within a date range"
            summary={[{ label: 'Movements', value: rows.length }]}
            columns={MOVEMENT_COLUMNS}
            rows={rows}
            noun="movements"
            loading={loading}
            emptyMessage="No movements found for selected filters."
        >
            <MovementFilters items={meta.items ?? []} onApply={reload} />
        </DesktopReport>
    );
}
