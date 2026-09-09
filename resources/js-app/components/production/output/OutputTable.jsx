import { Link } from 'react-router-dom';
import { formatDate, num } from '../formatters';

/** Blade's six columns, with the production order linked to its detail page. */
export default function OutputTable({ outputs }) {
    return (
        <div className="table-wrapper overflow-x-auto">
            <table className="table-base">
                <thead>
                    <tr>
                        <th>Production Order</th>
                        <th>Item</th>
                        <th>Warehouse</th>
                        <th className="text-right">Quantity</th>
                        <th>Output Date</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    {outputs.length === 0 && (
                        <tr>
                            <td colSpan={6} className="px-4 py-8 text-center text-gray-400">No production outputs recorded.</td>
                        </tr>
                    )}

                    {outputs.map((output) => (
                        <tr key={output.id}>
                            <td className="font-mono text-gray-700">
                                <Link to={`/app/production/orders/${output.production_order_id}`} className="text-blue-600 hover:underline">
                                    {output.production_order_number}
                                </Link>
                            </td>
                            <td className="text-gray-800">{output.item_name ?? ''}</td>
                            <td>{output.warehouse_name ?? ''}</td>
                            {/* Blade weighted this cell more than the issues page did. */}
                            <td className="text-right font-medium text-gray-800">{num(output.quantity)}</td>
                            <td>{formatDate(output.output_date)}</td>
                            <td className="text-gray-500 text-xs">{output.notes || '-'}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
