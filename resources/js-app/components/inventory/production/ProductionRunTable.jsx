import { money, qty } from '../../../currency';
import { formatDate } from '../movement/movementStyles';

export default function ProductionRunTable({ runs, onView }) {
    return (
        <div className="table-wrapper overflow-x-auto">
            <table className="table-base">
                <thead>
                    <tr>
                        <th>Run #</th>
                        <th>Date</th>
                        <th>Product</th>
                        <th className="text-right">Qty Made</th>
                        <th>Warehouse</th>
                        <th className="text-right">Total Cost</th>
                        <th className="text-right">Cost / Unit</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {runs.length === 0 && (
                        <tr>
                            <td colSpan={8} className="px-4 py-8 text-center text-gray-400">No production runs recorded.</td>
                        </tr>
                    )}
                    {runs.map((run) => (
                        <tr key={run.id}>
                            <td className="font-mono text-gray-700">{run.run_number}</td>
                            <td>{formatDate(run.production_date)}</td>
                            <td>
                                <div className="font-medium text-gray-800">{run.item_name}</div>
                                <div className="font-mono text-xs text-gray-400">{run.item_code}</div>
                            </td>
                            <td className="text-right text-gray-700">{qty(run.quantity)} {run.unit_of_measure}</td>
                            <td>{run.warehouse_name}</td>
                            <td className="text-right text-gray-700">{money(run.total_cost)}</td>
                            <td className="text-right text-gray-700">{money(run.unit_cost)}</td>
                            <td>
                                <button type="button" onClick={() => onView(run)} className="btn-primary btn-sm">View</button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
