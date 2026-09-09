import { Link } from 'react-router-dom';
import { STATUS_LABELS, badgeClassFor, formatDate, money } from './statuses';

/**
 * The Blade purchase-orders table, using the same `.table-wrapper` /
 * `.table-base` / `.badge-*` / `.btn-*` classes from resources/css/app.css that
 * it used — light slate header, right-aligned total, badged status, and
 * small View/Edit/Delete buttons.
 */
export default function PurchaseOrderTable({ orders, onEdit, onDelete }) {
    return (
        <div className="table-wrapper overflow-x-auto">
            <table className="table-base">
                <thead>
                    <tr>
                        <th>PO #</th>
                        <th>Supplier</th>
                        <th>Date</th>
                        <th>Expected Delivery</th>
                        <th className="text-right">Total Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {orders.length === 0 && (
                        <tr>
                            <td colSpan={7} className="px-4 py-8 text-center text-gray-400">
                                No purchase orders found.
                            </td>
                        </tr>
                    )}

                    {orders.map((order) => (
                        <tr key={order.id}>
                            <td className="font-mono text-gray-700">
                                <Link to={`/app/purchase/orders/${order.id}`} className="text-blue-600 hover:text-blue-800">
                                    {order.po_number}
                                </Link>
                            </td>
                            <td className="text-gray-800">{order.supplier_name ?? ''}</td>
                            <td>{formatDate(order.po_date)}</td>
                            <td>{formatDate(order.expected_delivery_date)}</td>
                            <td className="text-right font-medium text-gray-800">{money(order.total_amount)}</td>
                            <td>
                                <span className={badgeClassFor(order.status)}>
                                    {STATUS_LABELS[order.status] ?? order.status}
                                </span>
                            </td>
                            <td>
                                <div className="flex items-center gap-2">
                                    <Link to={`/app/purchase/orders/${order.id}`} className="btn-primary btn-sm">View</Link>
                                    <button type="button" onClick={() => onEdit(order)} className="btn-secondary btn-sm">Edit</button>
                                    <button type="button" onClick={() => onDelete(order)} className="btn-danger btn-sm">Delete</button>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
