import { amount, money, qty } from '../../../currency';
import { formatDate } from '../movement/movementStyles';

/** One run as recorded: what was made, from what, at what cost. */
export default function RunDetail({ run, compact = false }) {
    const facts = [
        ['Product', `${run.item_code} — ${run.item_name}`],
        ['Made', `${qty(run.quantity)} ${run.unit_of_measure ?? ''}`],
        ['Into', run.warehouse_name],
        ['Date', formatDate(run.production_date)],
        ['Total cost', money(run.total_cost)],
        [`Cost per ${run.unit_of_measure ?? 'unit'}`, money(run.unit_cost)],
        ['Recorded by', run.created_by_name ?? '—'],
    ];

    return (
        <div>
            <dl style={{ display: 'grid', gridTemplateColumns: compact ? '1fr' : '1fr 1fr', gap: '8px 20px', marginBottom: 16 }}>
                {facts.map(([label, value]) => (
                    <div key={label}>
                        <dt style={{ fontSize: 11.5, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '.04em' }}>{label}</dt>
                        <dd style={{ fontSize: 13.5, color: '#0f172a', fontWeight: 500 }}>{value}</dd>
                    </div>
                ))}
            </dl>
            {run.notes && <p style={{ fontSize: 13, color: '#475569', marginBottom: 12 }}>{run.notes}</p>}

            <h3 style={{ fontSize: 13.5, fontWeight: 700, color: '#1e293b', marginBottom: 6 }}>Raw materials used</h3>
            <div className="table-wrapper overflow-x-auto">
                <table className="table-base">
                    <thead>
                        <tr>
                            <th>Material</th>
                            {!compact && <th>From</th>}
                            <th className="text-right">Qty</th>
                            {!compact && <th className="text-right">Cost price</th>}
                            <th className="text-right">Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        {(run.lines ?? []).map((line) => (
                            <tr key={line.id}>
                                <td>{line.item_name}</td>
                                {!compact && <td>{line.warehouse_name}</td>}
                                <td className="text-right">{qty(line.quantity)} {line.unit_of_measure}</td>
                                {!compact && <td className="text-right">{amount(line.unit_cost)}</td>}
                                <td className="text-right">{amount(line.line_cost)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
