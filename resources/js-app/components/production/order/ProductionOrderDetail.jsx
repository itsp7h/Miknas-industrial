import { Link } from 'react-router-dom';
import { badgeClassFor, formatDate, num, statusLabel } from './orderStyles';

function Row({ label, children }) {
    return (
        <div className="flex justify-between">
            <dt className="text-gray-500">{label}</dt>
            <dd>{children}</dd>
        </div>
    );
}

function FlowTable({ title, rows, dateKey, actionLabel, actionTo }) {
    return (
        <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-6">
            <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 className="text-base font-semibold text-gray-700">{title}</h2>
                <Link to={actionTo} className="btn-primary btn-sm">{actionLabel}</Link>
            </div>
            <table className="table-base">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Warehouse</th>
                        <th className="text-right">Qty</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 && (
                        <tr>
                            <td colSpan={4} className="px-4 py-8 text-center text-gray-400">Nothing recorded yet.</td>
                        </tr>
                    )}
                    {rows.map((row) => (
                        <tr key={row.id}>
                            <td className="text-gray-800">{row.item_name ?? ''}</td>
                            <td>{row.warehouse_name ?? ''}</td>
                            <td className="text-right text-gray-700">{num(row.quantity)}</td>
                            <td>{formatDate(row[dateKey])}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/**
 * The four sections the Blade show page laid out: order details, the product's
 * bill of materials, material issues and production output. Blade's controller
 * passed only $productionOrder, so `$bom`, `$materialIssues` and `$outputs` were
 * never set and three of the four sections never rendered on that page.
 */
export default function ProductionOrderDetail({ order, compact = false }) {
    if (!order) return null;

    const bom = order.bom ?? [];
    const issues = order.material_issues ?? [];
    const outputs = order.outputs ?? [];

    return (
        <div>
            <div className={compact ? 'grid grid-cols-1 gap-4 mb-6' : 'grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6'}>
                <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                    <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Order Details</h2>
                    <dl className="space-y-3 text-sm">
                        <Row label="Order Number">
                            <span className="font-mono font-semibold text-gray-800">{order.order_number}</span>
                        </Row>
                        <Row label="Product"><span className="font-medium text-gray-800">{order.product_name ?? ''}</span></Row>
                        <Row label="Qty to Produce"><span className="text-gray-800">{num(order.quantity_to_produce)}</span></Row>
                        <Row label="Qty Produced"><span className="text-gray-800">{num(order.quantity_produced)}</span></Row>
                        <Row label="Outstanding"><span className="text-gray-800">{num(order.outstanding)}</span></Row>
                        <Row label="Production Date"><span className="text-gray-800">{formatDate(order.production_date) || '-'}</span></Row>
                        {order.completion_date && (
                            <Row label="Completed"><span className="text-gray-800">{formatDate(order.completion_date)}</span></Row>
                        )}
                        <Row label="Status">
                            <span className={badgeClassFor(order.status)}>{statusLabel(order.status)}</span>
                        </Row>
                        {order.notes && (
                            <div>
                                <dt className="text-gray-500">Notes</dt>
                                <dd className="text-gray-700 mt-1">{order.notes}</dd>
                            </div>
                        )}
                    </dl>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                    <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Bill of Materials</h2>
                    {bom.length === 0 && <p className="text-sm text-gray-400">No BOM defined for this product.</p>}
                    {bom.length > 0 && (
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-200">
                                    <th className="pb-2 text-left font-semibold text-gray-600">Material</th>
                                    <th className="pb-2 text-right font-semibold text-gray-600">Qty Required</th>
                                    <th className="pb-2 text-left font-semibold text-gray-600">UOM</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {bom.map((line) => (
                                    <tr key={line.id}>
                                        <td className="py-2 text-gray-800">{line.material_name ?? ''}</td>
                                        <td className="py-2 text-right text-gray-700">{num(line.quantity_required)}</td>
                                        <td className="py-2 text-gray-500 pl-2">{line.unit_of_measure}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>

            {/* Blade hid these sections entirely when empty, which left no way to
                reach the first issue or output from here. */}
            <FlowTable
                title="Material Issues" rows={issues} dateKey="issue_date"
                actionLabel="+ Issue Material"
                // The issues page reads this and preselects the order, the way
                // Blade's create link did.
                actionTo={`/app/production/material-issues?production_order_id=${order.id}`}
            />
            <FlowTable
                title="Production Output" rows={outputs} dateKey="output_date"
                actionLabel="+ Record Output" actionTo="/app/production/outputs"
            />
        </div>
    );
}
