import { STATUS_BACKGROUNDS, STATUS_COLOURS, STATUS_LABELS, formatDate, money } from './statuses';

function Badge({ status }) {
    return (
        <span style={{
            display: 'inline-block', padding: '2px 9px', borderRadius: 20, fontSize: 9.5,
            fontWeight: 700, letterSpacing: '.04em', marginTop: 4,
            background: STATUS_BACKGROUNDS[status] ?? STATUS_BACKGROUNDS.draft,
            color: STATUS_COLOURS[status] ?? STATUS_COLOURS.draft,
        }}>
            {STATUS_LABELS[status] ?? status}
        </span>
    );
}

const LABEL = { fontSize: 9.5, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '.04em' };
const VALUE = { fontSize: 12.5, fontWeight: 600, color: '#0f172a', marginTop: 2 };
const PLAIN = { ...VALUE, fontWeight: 400 };
const SECTION = {
    fontSize: 10, fontWeight: 700, color: '#64748b', textTransform: 'uppercase',
    letterSpacing: '.06em', marginBottom: 12,
};

/**
 * The LPO sheet from the Blade show page, which deliberately mirrors the
 * printable/PDF layout. Desktop and mobile differ only in outer chrome and
 * sheet width, so the facts live here once.
 */
export default function PurchaseOrderDetail({ order, compact = false }) {
    if (!order) return null;

    const supplier = order.supplier ?? null;
    const request = order.purchase_request ?? null;
    const items = order.items ?? [];
    const grns = order.goods_receipt_notes ?? [];
    const columns = compact ? '1fr' : 'repeat(2, 1fr)';

    return (
        <div>
            <div style={{
                maxWidth: 820, margin: '0 auto 24px', background: '#fff', borderRadius: 16,
                boxShadow: '0 4px 24px rgba(0,0,0,.08)', overflow: 'hidden',
            }}>
                <div style={{
                    display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start',
                    padding: compact ? '20px 18px 14px' : '28px 32px 18px', borderBottom: '2px solid #16a34a',
                    flexWrap: 'wrap', gap: 12,
                }}>
                    <div>
                        <div style={{ fontSize: 18, fontWeight: 700, color: '#0f172a' }}>
                            {order.company_name ?? 'SteelERP'}
                        </div>
                        <div style={{ fontSize: 10, color: '#64748b', marginTop: 1 }}>
                            {request?.project_name ?? 'Manufacturing & Trading'}
                        </div>
                    </div>
                    <div style={{ textAlign: compact ? 'left' : 'right' }}>
                        <div style={{ fontSize: 16, fontWeight: 700, color: '#1e293b' }}>Local Purchase Order</div>
                        <div style={{ fontSize: 10, color: '#64748b', marginTop: 3 }}>{order.po_number}</div>
                        <Badge status={order.status} />
                    </div>
                </div>

                <div style={{ padding: compact ? '16px 18px' : '20px 32px' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: columns, gap: 20 }}>
                        <div>
                            <div style={SECTION}>Supplier</div>
                            <div style={VALUE}>{supplier?.name ?? order.supplier_name ?? '—'}</div>
                            {supplier?.contact_person && <div style={PLAIN}>{supplier.contact_person}</div>}
                            {supplier?.address && <div style={PLAIN}>{supplier.address}</div>}
                            {supplier?.phone && <div style={PLAIN}>{supplier.phone}</div>}
                            {supplier?.email && <div style={PLAIN}>{supplier.email}</div>}
                        </div>
                        <div>
                            <div style={SECTION}>Order Details</div>
                            <div style={LABEL}>PO Date</div>
                            <div style={{ ...VALUE, marginBottom: 8 }}>{formatDate(order.po_date)}</div>
                            <div style={LABEL}>Expected Delivery</div>
                            <div style={VALUE}>{formatDate(order.expected_delivery_date)}</div>
                        </div>
                    </div>

                    {request && (
                        <div style={{ marginTop: 14 }}>
                            <div style={LABEL}>Reference MPR</div>
                            <div style={VALUE}>{request.request_number}</div>
                        </div>
                    )}
                    {order.notes && (
                        <div style={{ marginTop: 14 }}>
                            <div style={LABEL}>Notes</div>
                            <div style={PLAIN}>{order.notes}</div>
                        </div>
                    )}
                </div>

                <div style={{ padding: compact ? '0 18px 16px' : '0 32px 20px' }}>
                    <div style={SECTION}>Order Items</div>

                    {items.length === 0 && (
                        <p style={{ textAlign: 'center', padding: 20, color: '#94a3b8', fontSize: 12 }}>
                            No items on this order.
                        </p>
                    )}

                    {/* A five-column table is unreadable on a phone, so mobile stacks
                        each line into its own row instead of scrolling sideways. */}
                    {items.length > 0 && compact && items.map((line, index) => (
                        <div key={line.id} style={{ borderBottom: '1px solid #e2e8f0', padding: '8px 0' }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                                <span style={{ fontWeight: 600, color: '#0f172a', fontSize: 12.5 }}>
                                    {index + 1}. {line.item_name ?? '—'}
                                </span>
                                <span style={{ fontWeight: 700, fontSize: 12.5 }}>BD {money(line.total_amount)}</span>
                            </div>
                            <div style={{ fontSize: 11, color: '#64748b', marginTop: 2 }}>
                                {money(line.quantity)} {line.unit_of_measure ?? ''} × BD {money(line.rate)}
                            </div>
                        </div>
                    ))}

                    {items.length > 0 && !compact && (
                        <div style={{ overflowX: 'auto' }}>
                            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 11 }}>
                                <thead>
                                    <tr>
                                        {[
                                            ['#', '5%', 'left'],
                                            ['Item', '40%', 'left'],
                                            ['Quantity', '15%', 'right'],
                                            ['Rate', '20%', 'right'],
                                            ['Total', '20%', 'right'],
                                        ].map(([label, width, align]) => (
                                            <th key={label} style={{
                                                width, textAlign: align, background: '#1e293b', color: '#fff',
                                                fontWeight: 600, fontSize: 9.5, letterSpacing: '.05em',
                                                textTransform: 'uppercase', padding: '8px 10px',
                                            }}>
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {items.map((line, index) => (
                                        <tr key={line.id} style={{ background: index % 2 === 1 ? '#f8fafc' : undefined }}>
                                            <td style={{ padding: '7px 10px', color: '#94a3b8', textAlign: 'center', borderBottom: '1px solid #e2e8f0' }}>
                                                {index + 1}
                                            </td>
                                            <td style={{ padding: '7px 10px', fontWeight: 600, color: '#0f172a', borderBottom: '1px solid #e2e8f0' }}>
                                                {line.item_name ?? '—'}
                                            </td>
                                            <td style={{ padding: '7px 10px', textAlign: 'right', color: '#334155', borderBottom: '1px solid #e2e8f0' }}>
                                                {money(line.quantity)} {line.unit_of_measure ?? ''}
                                            </td>
                                            <td style={{ padding: '7px 10px', textAlign: 'right', color: '#334155', borderBottom: '1px solid #e2e8f0' }}>
                                                BD {money(line.rate)}
                                            </td>
                                            <td style={{ padding: '7px 10px', textAlign: 'right', color: '#334155', borderBottom: '1px solid #e2e8f0' }}>
                                                BD {money(line.total_amount)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colSpan={4} style={{ padding: 10, fontWeight: 700, textAlign: 'right' }}>Total Amount</td>
                                        <td style={{ padding: 10, fontWeight: 700, textAlign: 'right' }}>BD {money(order.total_amount)}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    )}

                    {compact && items.length > 0 && (
                        <div style={{ display: 'flex', justifyContent: 'space-between', paddingTop: 10, fontWeight: 700 }}>
                            <span>Total Amount</span>
                            <span>BD {money(order.total_amount)}</span>
                        </div>
                    )}
                </div>

                <div style={{
                    marginTop: 8, padding: compact ? '12px 18px 20px' : '14px 32px 24px',
                    borderTop: '1px solid #e2e8f0', display: 'flex', justifyContent: 'space-between',
                    fontSize: 9.5, color: '#94a3b8', gap: 8, flexWrap: 'wrap',
                }}>
                    <span>SteelERP — Confidential</span>
                    <span>Issued by: {order.created_by_name ?? '—'}</span>
                </div>
            </div>

            {grns.length > 0 && (
                <div style={{
                    maxWidth: 820, margin: '0 auto', background: '#fff', borderRadius: 16,
                    boxShadow: '0 4px 24px rgba(0,0,0,.08)', overflow: 'hidden',
                }}>
                    <div style={{ padding: '16px 24px', borderBottom: '1px solid #e2e8f0' }}>
                        <h2 style={{ fontSize: 14, fontWeight: 700, color: '#0f172a', margin: 0 }}>
                            Goods Receipt Notes
                        </h2>
                    </div>
                    <div style={{ padding: '8px 24px 16px' }}>
                        {grns.map((grn) => (
                            <div key={grn.id} style={{
                                display: 'flex', justifyContent: 'space-between', gap: 8,
                                padding: '8px 0', borderBottom: '1px solid #f1f5f9', fontSize: 13, flexWrap: 'wrap',
                            }}>
                                <span style={{ fontFamily: 'monospace', color: '#334155' }}>{grn.grn_number}</span>
                                <span style={{ color: '#64748b' }}>{grn.warehouse_name ?? '—'}</span>
                                <span style={{ color: '#94a3b8' }}>{formatDate(grn.received_date)}</span>
                                <a href={`/purchase/grns/${grn.id}`} style={{ color: '#2563eb' }}>View</a>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
