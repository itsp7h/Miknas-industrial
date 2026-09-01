import { methodLabel, formatDate, money } from './paymentStyles';

/**
 * The Blade payments table plus an Actions column. Blade had none, and its
 * edit/show routes rendered views that did not exist — so a recorded payment
 * could never be corrected.
 */
export default function SupplierPaymentTable({ payments, onEdit, onDelete }) {
    return (
        <div className="table-wrapper overflow-x-auto">
            <table className="table-base">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Supplier</th>
                        <th>Payment Date</th>
                        <th className="text-right">Amount</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {payments.length === 0 && (
                        <tr>
                            <td colSpan={7} className="px-4 py-8 text-center text-gray-400">No payments recorded.</td>
                        </tr>
                    )}

                    {payments.map((payment) => (
                        <tr key={payment.id}>
                            <td className="font-mono text-gray-700">{payment.invoice_number ?? '-'}</td>
                            <td className="text-gray-800">{payment.supplier_name ?? ''}</td>
                            <td>{formatDate(payment.payment_date)}</td>
                            <td className="text-right font-medium text-gray-800">{money(payment.amount)}</td>
                            <td>{methodLabel(payment.payment_method)}</td>
                            <td>{payment.reference_number || '-'}</td>
                            <td>
                                <div className="flex items-center gap-2">
                                    <button type="button" onClick={() => onEdit(payment)} className="btn-secondary btn-sm">Edit</button>
                                    <button type="button" onClick={() => onDelete(payment)} className="btn-danger btn-sm">Delete</button>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
