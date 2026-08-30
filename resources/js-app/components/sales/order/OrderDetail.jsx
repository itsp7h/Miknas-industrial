import { STATUS_LABELS, STATUS_COLOURS, money } from './statuses';

/**
 * Shared body for the order detail page. The desktop and mobile pages differ
 * in chrome and layout width, but the facts shown are identical — keeping them
 * in one place stops the two drifting apart.
 */
export default function OrderDetail({ order, compact = false }) {
    if (!order) return null;

    const rows = order.items ?? [];
    const notes = order.delivery_notes ?? [];
    const invoices = order.invoices ?? [];

    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap', gap: 8, marginBottom: 12 }}>
                <div>
                    <div style={{ fontSize: compact ? 18 : 22, fontWeight: 700 }}>{order.order_number}</div>
                    <div style={{ fontSize: 13, color: '#64748b' }}>{order.customer_name ?? '—'}</div>
                </div>
                <div style={{ textAlign: 'right' }}>
                    <div style={{ fontSize: compact ? 18 : 22, fontWeight: 700 }}>{money(order.total_amount)}</div>
                    <div style={{ fontSize: 13, fontWeight: 600, color: STATUS_COLOURS[order.status] }}>
                        {STATUS_LABELS[order.status] ?? order.status}
                    </div>
                </div>
            </div>

            <div style={{ display: 'flex', gap: 24, flexWrap: 'wrap', fontSize: 13, color: '#64748b', marginBottom: 16 }}>
                <span>Ordered {order.order_date}</span>
                {order.delivery_date && <span>Delivery {order.delivery_date}</span>}
            </div>

            <h3 style={{ fontSize: 14, fontWeight: 600, marginBottom: 8 }}>Line Items</h3>
            {rows.length === 0 && <p style={{ fontSize: 13, color: '#64748b' }}>No line items.</p>}
            {rows.map((line) => (
                <div key={line.id} style={{ border: '1px solid #e2e8f0', borderRadius: 8, padding: 10, marginBottom: 6 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                        <span style={{ fontWeight: 600 }}>{line.item_name ?? '—'}</span>
                        <span style={{ fontWeight: 600 }}>{money(line.total_amount)}</span>
                    </div>
                    <div style={{ fontSize: 12, color: '#64748b' }}>
                        {line.quantity} × {money(line.price)} · delivered {line.quantity_delivered ?? 0}
                    </div>
                </div>
            ))}

            <h3 style={{ fontSize: 14, fontWeight: 600, margin: '16px 0 8px' }}>Delivery Notes</h3>
            {notes.length === 0 && <p style={{ fontSize: 13, color: '#64748b' }}>Nothing dispatched yet.</p>}
            {notes.map((note) => (
                <div key={note.id} style={{ fontSize: 13, padding: '4px 0' }}>
                    {note.delivery_number} · {note.delivery_date} · {note.status}
                </div>
            ))}

            <h3 style={{ fontSize: 14, fontWeight: 600, margin: '16px 0 8px' }}>Invoices</h3>
            {invoices.length === 0 && <p style={{ fontSize: 13, color: '#64748b' }}>Not invoiced yet.</p>}
            {invoices.map((invoice) => (
                <div key={invoice.id} style={{ fontSize: 13, padding: '4px 0' }}>
                    {invoice.invoice_number} · {invoice.invoice_date} · {money(invoice.total_amount)} · {invoice.status}
                </div>
            ))}

            {order.notes && (
                <>
                    <h3 style={{ fontSize: 14, fontWeight: 600, margin: '16px 0 8px' }}>Notes</h3>
                    <p style={{ fontSize: 13, color: '#475569' }}>{order.notes}</p>
                </>
            )}
        </div>
    );
}
