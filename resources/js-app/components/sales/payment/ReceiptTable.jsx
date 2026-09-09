import { formatDate, money } from '../order/statuses';
import { methodLabel } from './methods';

/** Blade's six columns, led by the customer rather than the date. */
export default function ReceiptTable({ receipts }) {
    return (
        <div className="table-wrapper overflow-x-auto">
            <table className="table-base">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th className="text-right">Amount</th>
                        <th>Method</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    {receipts.length === 0 && (
                        <tr>
                            <td colSpan={6} className="px-4 py-8 text-center text-gray-400">No receipts recorded.</td>
                        </tr>
                    )}

                    {receipts.map((receipt) => (
                        <tr key={receipt.id}>
                            <td className="text-gray-800">{receipt.customer_name ?? ''}</td>
                            <td className="font-mono text-gray-700">{receipt.invoice_number ?? '-'}</td>
                            <td className="text-gray-600">{formatDate(receipt.receipt_date)}</td>
                            <td className="text-right font-medium text-gray-800">{money(receipt.amount)}</td>
                            <td className="text-gray-600">{methodLabel(receipt.payment_method)}</td>
                            <td className="text-gray-600">{receipt.reference_number || '-'}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
