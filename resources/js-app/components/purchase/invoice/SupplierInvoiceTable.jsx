import { Link } from 'react-router-dom';
import { STATUS_LABELS, badgeClassFor, formatDate, money } from './invoiceStyles';

/** The Blade supplier-invoices table, same classes and column alignment. */
export default function SupplierInvoiceTable({ invoices, onEdit, onDelete }) {
    return (
        <div className="table-wrapper overflow-x-auto">
            <table className="table-base">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Supplier</th>
                        <th>PO #</th>
                        <th>Date</th>
                        <th className="text-right">Total</th>
                        <th className="text-right">Paid</th>
                        <th className="text-right">Outstanding</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {invoices.length === 0 && (
                        <tr>
                            <td colSpan={9} className="px-4 py-8 text-center text-gray-400">No invoices found.</td>
                        </tr>
                    )}

                    {invoices.map((invoice) => {
                        const outstanding = Number(invoice.outstanding ?? 0);

                        return (
                            <tr key={invoice.id}>
                                <td className="font-mono text-gray-700">{invoice.invoice_number}</td>
                                <td className="text-gray-800">{invoice.supplier_name ?? ''}</td>
                                <td className="font-mono text-gray-600">{invoice.po_number ?? '-'}</td>
                                <td>{formatDate(invoice.invoice_date)}</td>
                                <td className="text-right text-gray-800">{money(invoice.total_amount)}</td>
                                <td className="text-right text-green-700">{money(invoice.paid_amount)}</td>
                                <td className={`text-right ${outstanding > 0 ? 'text-red-600 font-semibold' : 'text-gray-500'}`}>
                                    {money(invoice.outstanding)}
                                </td>
                                <td>
                                    <span className={badgeClassFor(invoice.status)}>
                                        {STATUS_LABELS[invoice.status] ?? invoice.status}
                                    </span>
                                </td>
                                <td>
                                    <div className="flex items-center gap-2">
                                        <Link to={`/app/purchase/payments?invoice_id=${invoice.id}`} className="btn-success btn-sm">Pay</Link>
                                        <button type="button" onClick={() => onEdit(invoice)} className="btn-secondary btn-sm">Edit</button>
                                        <button type="button" onClick={() => onDelete(invoice)} className="btn-danger btn-sm">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
