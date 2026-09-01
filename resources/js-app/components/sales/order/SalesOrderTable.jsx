import { Link } from 'react-router-dom';
import { badgeClassFor, formatDate, money, statusLabel } from './statuses';

/**
 * Blade's six columns. Edit and Delete are gated on draft: Blade offered them on
 * any order, but a confirmed order is an agreement with the customer and the API
 * refuses to change it, so offering the button only produced a rejection.
 */
export default function SalesOrderTable({ orders, onEdit, onConfirm, onDelete }) {
    return (
        <div className="table-wrapper overflow-x-auto">
            <table className="table-base">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th className="text-right">Total</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {orders.length === 0 && (
                        <tr>
                            <td colSpan={6} className="px-4 py-8 text-center text-gray-400">No sales orders found.</td>
                        </tr>
                    )}

                    {orders.map((order) => (
                        <tr key={order.id}>
                            <td className="font-mono text-gray-700">{order.order_number}</td>
                            <td className="font-medium text-gray-800">{order.customer_name ?? ''}</td>
                            <td>{formatDate(order.order_date)}</td>
                            <td className="text-right font-medium text-gray-800">{money(order.total_amount)}</td>
                            <td>
                                <span className={badgeClassFor(order.status)}>{statusLabel(order.status)}</span>
                            </td>
                            <td>
                                <div className="flex items-center gap-2 flex-wrap">
                                    <Link to={`/app/sales/orders/${order.id}`} className="btn-primary btn-sm">View</Link>
                                    {order.status === 'draft' && (
                                        <button type="button" onClick={() => onConfirm(order)} className="btn-primary btn-sm">Confirm</button>
                                    )}
                                    {order.status === 'draft' && (
                                        <button type="button" onClick={() => onEdit(order)} className="btn-secondary btn-sm">Edit</button>
                                    )}
                                    {order.status === 'draft' && (
                                        <button type="button" onClick={() => onDelete(order)} className="btn-danger btn-sm">Delete</button>
                                    )}
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
