import { Link } from 'react-router-dom';
import { formatDate, num } from '../formatters';

/** Blade's six columns, with the production order as a link into its detail page. */
export default function MaterialIssueTable({ issues }) {
    return (
        <div className="table-wrapper overflow-x-auto">
            <table className="table-base">
                <thead>
                    <tr>
                        <th>Production Order</th>
                        <th>Item</th>
                        <th>Warehouse</th>
                        <th className="text-right">Quantity</th>
                        <th>Issue Date</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    {issues.length === 0 && (
                        <tr>
                            <td colSpan={6} className="px-4 py-8 text-center text-gray-400">No material issues found.</td>
                        </tr>
                    )}

                    {issues.map((issue) => (
                        <tr key={issue.id}>
                            <td className="font-mono text-gray-700">
                                <Link to={`/app/production/orders/${issue.production_order_id}`} className="text-blue-600 hover:underline">
                                    {issue.production_order_number}
                                </Link>
                            </td>
                            <td className="text-gray-800">{issue.item_name ?? ''}</td>
                            <td>{issue.warehouse_name ?? ''}</td>
                            <td className="text-right text-gray-700">{num(issue.quantity)}</td>
                            <td>{formatDate(issue.issue_date)}</td>
                            <td className="text-gray-500 text-xs">{issue.notes || '-'}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
