import { Link } from 'react-router-dom';
import { formatDate } from '../order/statuses';
import { badgeClassFor, statusLabel } from './statuses';

/** Blade's seven columns, with the sales order linked to its detail page. */
export default function DeliveryNoteTable({ notes, onDispatch, onEdit, onDelete }) {
    return (
        <div className="table-wrapper overflow-x-auto">
            <table className="table-base">
                <thead>
                    <tr>
                        <th>DN #</th>
                        <th>Sales Order</th>
                        <th>Customer</th>
                        <th>Warehouse</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {notes.length === 0 && (
                        <tr>
                            <td colSpan={7} className="px-4 py-8 text-center text-gray-400">No delivery notes found.</td>
                        </tr>
                    )}

                    {notes.map((note) => (
                        <tr key={note.id}>
                            <td className="font-mono text-gray-700">{note.delivery_number}</td>
                            <td className="font-mono text-gray-600">
                                <Link to={`/app/sales/orders/${note.sales_order_id}`} className="text-blue-600 hover:underline">
                                    {note.order_number}
                                </Link>
                            </td>
                            <td className="text-gray-800">{note.customer_name ?? ''}</td>
                            <td className="text-gray-700">{note.warehouse_name ?? ''}</td>
                            <td className="text-gray-600">{formatDate(note.delivery_date)}</td>
                            <td>
                                <span className={badgeClassFor(note.status)}>{statusLabel(note.status)}</span>
                            </td>
                            <td>
                                <div className="flex items-center gap-2 flex-wrap">
                                    {note.status === 'draft' && (
                                        <button type="button" onClick={() => onDispatch(note)} className="btn-success btn-sm">Dispatch</button>
                                    )}
                                    {/* Editing or deleting a dispatched note would leave
                                        moved stock and raised order quantities behind. */}
                                    {note.status === 'draft' && (
                                        <button type="button" onClick={() => onEdit(note)} className="btn-secondary btn-sm">Edit</button>
                                    )}
                                    {note.status === 'draft' && (
                                        <button type="button" onClick={() => onDelete(note)} className="btn-danger btn-sm">Delete</button>
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
