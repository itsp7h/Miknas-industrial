import { Link } from 'react-router-dom';
import { badgeClassFor, formatDate, num, statusLabel } from './orderStyles';

/**
 * The Blade production-orders table, with the actions the earlier React port had
 * dropped — only Complete had survived it. Delete has no Blade counterpart on
 * this page but the endpoint exists and the previous React page offered it, so
 * it stays.
 */
export default function ProductionOrderTable({ orders, onEdit, onStart, onComplete, onDelete }) {
    return (
        <div className="table-wrapper overflow-x-auto">
            <table className="table-base">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Product</th>
                        <th className="text-right">Qty to Produce</th>
                        <th className="text-right">Produced</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {orders.length === 0 && (
                        <tr>
                            <td colSpan={7} className="px-4 py-8 text-center text-gray-400">No production orders found.</td>
                        </tr>
                    )}

                    {orders.map((order) => (
                        <tr key={order.id}>
                            <td className="font-mono text-gray-700">{order.order_number}</td>
                            <td className="font-medium text-gray-800">{order.product_name ?? ''}</td>
                            <td className="text-right text-gray-700">{num(order.quantity_to_produce)}</td>
                            <td className="text-right text-gray-700">{num(order.quantity_produced)}</td>
                            <td>{formatDate(order.production_date)}</td>
                            <td>
                                <span className={badgeClassFor(order.status)}>{statusLabel(order.status)}</span>
                            </td>
                            <td>
                                <div className="flex items-center gap-2 flex-wrap">
                                    <Link to={`/app/production/orders/${order.id}`} className="btn-primary btn-sm">View</Link>
                                    {/* Blade guarded Start on 'pending', a value the status
                                        enum does not contain — so Start never rendered. */}
                                    {order.status === 'planned' && (
                                        <button type="button" onClick={() => onStart(order)} className="btn-primary btn-sm">Start</button>
                                    )}
                                    {order.status === 'in_progress' && (
                                        <button type="button" onClick={() => onComplete(order)} className="btn-success btn-sm">Complete</button>
                                    )}
                                    {order.status === 'planned' && (
                                        <button type="button" onClick={() => onEdit(order)} className="btn-secondary btn-sm">Edit</button>
                                    )}
                                    {order.status === 'planned' && (
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
