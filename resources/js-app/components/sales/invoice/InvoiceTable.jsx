import { Link } from 'react-router-dom';
import { formatDate, money } from '../order/statuses';
import { badgeClassFor, statusLabel } from './statuses';

/**
 * Blade's nine columns. The port had seven: it dropped the SO # entirely, and
 * with it every action — there was no way to receive a payment, edit or delete
 * from this page.
 */
export default function InvoiceTable({ invoices, onEdit, onDelete }) {
    return (
        <div className="table-wrapper overflow-x-auto">
            <table className="table-base">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Customer</th>
                        <th>SO #</th>
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

                    {invoices.map((invoice) => (
                        <tr key={invoice.id}>
                            <td className="font-mono text-gray-700">{invoice.invoice_number}</td>
                            <td className="text-gray-800">{invoice.customer_name ?? ''}</td>
                            <td className="font-mono text-gray-600">
                                {invoice.sales_order_id
                                    ? (
                                        <Link to={`/app/sales/orders/${invoice.sales_order_id}`} className="text-blue-600 hover:underline">
                                            {invoice.order_number}
                                        </Link>
                                    )
                                    : '-'}
                            </td>
                            <td className="text-gray-600">{formatDate(invoice.invoice_date)}</td>
                            <td className="text-right text-gray-800">{money(invoice.total_amount)}</td>
                            <td className="text-right text-green-700">{money(invoice.paid_amount)}</td>
                            <td className={`text-right ${Number(invoice.balance_due ?? 0) > 0 ? 'text-red-600 font-semibold' : 'text-gray-500'}`}>
                                {money(invoice.balance_due)}
                            </td>
                            <td>
                                <span className={badgeClassFor(invoice.status)}>{statusLabel(invoice.status)}</span>
                            </td>
                            <td>
                                <div className="flex items-center gap-2 flex-wrap">
                                    {/* Blade's Receive link, which opened the payment form
                                        with this invoice preselected. */}
                                    {invoice.status !== 'paid' && (
                                        <Link to={`/app/sales/payments?invoice_id=${invoice.id}`} className="btn-success btn-sm">Receive</Link>
                                    )}
                                    <button type="button" onClick={() => onEdit(invoice)} className="btn-secondary btn-sm">Edit</button>
                                    {/* Deleting an invoice that has been paid against would
                                        strand the receipts and the customer's balance. */}
                                    {Number(invoice.paid_amount ?? 0) === 0 && (
                                        <button type="button" onClick={() => onDelete(invoice)} className="btn-danger btn-sm">Delete</button>
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
