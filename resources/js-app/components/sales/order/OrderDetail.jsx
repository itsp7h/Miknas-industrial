import { badgeClassFor, formatDate, money, statusLabel } from './statuses';

function Field({ label, children }) {
    return (
        <div>
            <dt className="text-gray-500">{label}</dt>
            <dd className="font-medium text-gray-800">{children}</dd>
        </div>
    );
}

function TableCard({ title, head, children }) {
    return (
        <div className="card mb-6 overflow-hidden">
            <div className="card-header">
                <h2 className="text-base font-semibold text-gray-700">{title}</h2>
            </div>
            <div className="overflow-x-auto">
                <table className="table-base">
                    <thead><tr>{head}</tr></thead>
                    {children}
                </table>
            </div>
        </div>
    );
}

/**
 * The Blade show page: order details beside a customer card, then the line items
 * with a total row, then delivery notes and invoices. The first React port
 * flattened all of it into a stack of plain divs and dropped the customer's
 * contact details entirely.
 *
 * Desktop and mobile differ in chrome and column widths, not in the facts shown,
 * so both render this.
 */
export default function OrderDetail({ order, compact = false }) {
    if (!order) return null;

    const lines = order.items ?? [];
    const notes = order.delivery_notes ?? [];
    const invoices = order.invoices ?? [];
    const customer = order.customer;

    return (
        <div>
            <div className={compact ? 'grid grid-cols-1 gap-4 mb-6' : 'grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6'}>
                <div className={compact ? 'card card-body' : 'lg:col-span-2 card card-body'}>
                    <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Order Details</h2>
                    <dl className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt className="text-gray-500">Order Number</dt>
                            <dd className="font-mono font-semibold text-gray-800">{order.order_number}</dd>
                        </div>
                        <div>
                            <dt className="text-gray-500">Status</dt>
                            <dd>
                                <span className={badgeClassFor(order.status)}>{statusLabel(order.status)}</span>
                            </dd>
                        </div>
                        <Field label="Order Date">{formatDate(order.order_date) || '-'}</Field>
                        <Field label="Delivery Date">{formatDate(order.delivery_date) || '-'}</Field>
                    </dl>
                </div>

                <div className="card card-body">
                    <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Customer</h2>
                    {!customer && <p className="text-sm text-gray-400">No customer on this order.</p>}
                    {customer && (
                        <dl className="space-y-2 text-sm">
                            <div><dt className="text-gray-500">Name</dt><dd className="font-semibold text-gray-800">{customer.name}</dd></div>
                            <div><dt className="text-gray-500">Contact</dt><dd className="text-gray-700">{customer.contact_person}</dd></div>
                            <div><dt className="text-gray-500">Email</dt><dd className="text-gray-700">{customer.email}</dd></div>
                            <div><dt className="text-gray-500">Phone</dt><dd className="text-gray-700">{customer.phone}</dd></div>
                        </dl>
                    )}
                </div>
            </div>

            <TableCard
                title="Order Items"
                head={(
                    <>
                        <th>Product</th>
                        <th className="text-right">Quantity</th>
                        <th className="text-right">Unit Price</th>
                        <th className="text-right">Total</th>
                    </>
                )}
            >
                <>
                    <tbody>
                        {lines.length === 0 && (
                            <tr><td colSpan={4} className="px-4 py-6 text-center text-gray-400">No items.</td></tr>
                        )}
                        {lines.map((line) => (
                            <tr key={line.id}>
                                <td className="text-gray-800">{line.item_name ?? ''}</td>
                                <td className="text-right text-gray-600">
                                    {money(line.quantity)}
                                    {/* Not in Blade, but it is the only place the
                                        shipped-so-far figure appears. */}
                                    {Number(line.quantity_delivered ?? 0) > 0 && (
                                        <span className="text-gray-400" style={{ fontSize: 12 }}>
                                            {' '}({money(line.quantity_delivered)} delivered)
                                        </span>
                                    )}
                                </td>
                                <td className="text-right text-gray-600">{money(line.price)}</td>
                                <td className="text-right font-medium text-gray-800">{money(line.total_amount)}</td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot className="bg-gray-50">
                        <tr>
                            <td colSpan={3} className="px-4 py-3 text-right font-semibold text-gray-700">Total</td>
                            <td className="px-4 py-3 text-right font-bold text-gray-900">{money(order.total_amount)}</td>
                        </tr>
                    </tfoot>
                </>
            </TableCard>

            {/* Blade hid these two sections when empty; they stay, saying so,
                since "nothing dispatched yet" is itself the answer. */}
            <TableCard
                title="Delivery Notes"
                head={(
                    <>
                        <th>DN #</th>
                        <th>Warehouse</th>
                        <th>Delivery Date</th>
                        <th>Status</th>
                    </>
                )}
            >
                <tbody>
                    {notes.length === 0 && (
                        <tr><td colSpan={4} className="px-4 py-6 text-center text-gray-400">Nothing dispatched yet.</td></tr>
                    )}
                    {notes.map((note) => (
                        <tr key={note.id}>
                            <td className="font-mono text-gray-700">{note.delivery_number}</td>
                            <td className="text-gray-700">{note.warehouse_name ?? ''}</td>
                            <td className="text-gray-600">{formatDate(note.delivery_date)}</td>
                            <td><span className={note.status === 'dispatched' ? 'badge-violet' : 'badge-gray'}>{statusLabel(note.status)}</span></td>
                        </tr>
                    ))}
                </tbody>
            </TableCard>

            <TableCard
                title="Invoices"
                head={(
                    <>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th className="text-right">Total</th>
                        <th>Status</th>
                    </>
                )}
            >
                <tbody>
                    {invoices.length === 0 && (
                        <tr><td colSpan={4} className="px-4 py-6 text-center text-gray-400">Not invoiced yet.</td></tr>
                    )}
                    {invoices.map((invoice) => (
                        <tr key={invoice.id}>
                            <td className="font-mono text-gray-700">{invoice.invoice_number}</td>
                            <td className="text-gray-600">{formatDate(invoice.invoice_date)}</td>
                            <td className="text-right font-medium text-gray-800">{money(invoice.total_amount)}</td>
                            <td><span className={invoice.status === 'paid' ? 'badge-green' : 'badge-yellow'}>{statusLabel(invoice.status)}</span></td>
                        </tr>
                    ))}
                </tbody>
            </TableCard>

            {order.notes && (
                <div className="card card-body">
                    <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Notes</h2>
                    <p className="text-sm text-gray-700">{order.notes}</p>
                </div>
            )}
        </div>
    );
}
