import { Link } from 'react-router-dom';
import { STATUS_LABELS, badgeClassFor, formatDate } from './grnStyles';

/** The Blade GRN index table, using the same .table-base / .badge-* / .btn-* classes. */
export default function GrnTable({ grns, onConfirm, onDelete }) {
    return (
        <div className="table-wrapper overflow-x-auto">
            <table className="table-base">
                <thead>
                    <tr>
                        <th>GRN #</th>
                        <th>PO #</th>
                        <th>Supplier</th>
                        <th>Warehouse</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {grns.length === 0 && (
                        <tr>
                            <td colSpan={7} className="px-4 py-8 text-center text-gray-400">No GRNs found.</td>
                        </tr>
                    )}

                    {grns.map((grn) => (
                        <tr key={grn.id}>
                            <td className="font-mono text-gray-700">
                                <Link to={`/app/purchase/grns/${grn.id}`} className="text-blue-600 hover:text-blue-800">
                                    {grn.grn_number}
                                </Link>
                            </td>
                            <td className="font-mono text-gray-600">
                                <Link to={`/app/purchase/orders/${grn.purchase_order_id}`} className="text-blue-600 hover:text-blue-800">
                                    {grn.po_number}
                                </Link>
                            </td>
                            <td className="text-gray-800">{grn.supplier_name ?? ''}</td>
                            <td className="text-gray-700">{grn.warehouse_name ?? ''}</td>
                            <td>{formatDate(grn.received_date)}</td>
                            <td>
                                <span className={badgeClassFor(grn.status)}>
                                    {STATUS_LABELS[grn.status] ?? grn.status}
                                </span>
                            </td>
                            <td>
                                <div className="flex items-center gap-2">
                                    <Link to={`/app/purchase/grns/${grn.id}`} className="btn-primary btn-sm">View</Link>
                                    {/* Confirming is what actually receives the stock. The
                                        Blade pages never offered it. */}
                                    {grn.status !== 'confirmed' && (
                                        <button type="button" onClick={() => onConfirm(grn)} className="btn-success btn-sm">
                                            Confirm
                                        </button>
                                    )}
                                    {grn.status !== 'confirmed' && (
                                        <button type="button" onClick={() => onDelete(grn)} className="btn-danger btn-sm">
                                            Delete
                                        </button>
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
