import { Link } from 'react-router-dom';
import { STATUS_LABELS, badgeClassFor, formatDate, qty } from './grnStyles';

function Row({ label, children }) {
    return (
        <div className="flex justify-between">
            <dt className="text-gray-500">{label}</dt>
            <dd className="text-gray-800 m-0">{children}</dd>
        </div>
    );
}

/** The Blade GRN show page: a details card plus the received-items table. */
export default function GrnDetail({ grn, compact = false }) {
    if (!grn) return null;

    const items = grn.items ?? [];

    return (
        <div>
            <div className={`grid grid-cols-1 ${compact ? '' : 'lg:grid-cols-2'} gap-4 mb-6`}>
                <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                    <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">GRN Details</h2>
                    <dl className="space-y-3 text-sm">
                        <Row label="GRN Number">
                            <span className="font-mono font-semibold">{grn.grn_number}</span>
                        </Row>
                        <Row label="Purchase Order">
                            <Link to={`/app/purchase/orders/${grn.purchase_order_id}`} className="font-mono text-blue-600 hover:underline">
                                {grn.po_number}
                            </Link>
                        </Row>
                        <Row label="Supplier"><span className="font-medium">{grn.supplier_name ?? '-'}</span></Row>
                        <Row label="Warehouse">{grn.warehouse_name ?? '-'}</Row>
                        <Row label="Received Date">{formatDate(grn.received_date)}</Row>
                        <Row label="Status">
                            <span className={badgeClassFor(grn.status)}>
                                {STATUS_LABELS[grn.status] ?? grn.status}
                            </span>
                        </Row>
                        {grn.received_by_name && <Row label="Received By">{grn.received_by_name}</Row>}
                        {grn.notes && (
                            <div>
                                <dt className="text-gray-500">Notes</dt>
                                <dd className="text-gray-700 mt-1">{grn.notes}</dd>
                            </div>
                        )}
                    </dl>
                </div>
            </div>

            <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div className="px-6 py-4 border-b border-gray-200">
                    <h2 className="text-base font-semibold text-gray-700">Items Received</h2>
                </div>
                <table className="table-base">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th className="text-right">PO Qty</th>
                            <th className="text-right">Qty Received</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 && (
                            <tr><td colSpan={3} className="px-4 py-6 text-center text-gray-400">No items recorded.</td></tr>
                        )}
                        {items.map((item) => (
                            <tr key={item.id}>
                                <td className="text-gray-800">{item.item_name ?? ''}</td>
                                {/* Read from the linked PO line; the Blade page printed a
                                    non-existent column here and always showed 0.00. */}
                                <td className="text-right text-gray-600">{qty(item.quantity_ordered)}</td>
                                <td className="text-right font-medium text-gray-800">{qty(item.quantity_received)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
