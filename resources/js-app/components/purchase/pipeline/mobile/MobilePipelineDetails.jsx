import { Avatar, Card, DocNo, ListRow, Pill, SectionLabel } from '../../../mobile/ui';
import { C } from '../../../mobile/theme';
import { INVITATION_STATUS, PO_STATUS, bd } from '../pipelineStyles';
import { formatRequiredDate, formatRequiredWhen } from '../../requests/requiredWhen';

// The phone's cards under the timeline (SteelERP-Mobile-Designs-V2): the
// same facts as the desktop PipelineSidebar, in the same order, as the
// design's labelled rows and list cards.

const STATUS_TONE = { approved: 'green', rejected: 'red', pending: 'amber', ordered: 'blue' };
const INVITATION_TONE = { pending: 'slate', sent: 'blue', opened: 'indigo', submitted: 'green', declined: 'red' };
const PO_TONE = { draft: 'slate', sent: 'blue', received: 'green', cancelled: 'red' };

const capitalise = (value) => (value ? value.charAt(0).toUpperCase() + value.slice(1) : '—');

function Detail({ label, children, last = false, valueStyle }) {
    return (
        <div style={{
            display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 16, padding: '13px 0',
            borderBottom: last ? 0 : `1px solid ${C.hairline}`, fontSize: 15,
        }}>
            <span style={{ color: C.muted, flexShrink: 0 }}>{label}</span>
            <span style={{ fontWeight: 500, textAlign: 'right', minWidth: 0, overflowWrap: 'anywhere', ...valueStyle }}>{children}</span>
        </div>
    );
}

function urgencyStyle(text) {
    return /urgent|critical/i.test(text ?? '') ? { color: '#B91C1C', fontWeight: 600 } : undefined;
}

export default function MobilePipelineDetails({ request: r }) {
    const details = [
        r.company_name && ['Company', r.company_name],
        r.project_name && ['Project', r.project_name],
        r.requested_by_name && ['Requested by', r.requested_by_name],
        r.location && ['Location', r.location],
        r.required_date_text && ['Urgency', formatRequiredWhen(r.required_date_text), urgencyStyle(r.required_date_text)],
        r.required_date && ['Required date', formatRequiredDate(r.required_date)],
        r.verified_by_name && ['Verified by', r.verified_by_name],
    ].filter(Boolean);

    return (
        <>
            <SectionLabel zone="purchase">Request details</SectionLabel>
            <Card style={{ padding: '0 16px' }}>
                {details.map(([label, value, style]) => (
                    <Detail key={label} label={label} valueStyle={style}>{value}</Detail>
                ))}
                <Detail label="Status" last>
                    <Pill tone={STATUS_TONE[r.status] ?? 'slate'}>{capitalise(r.status)}</Pill>
                </Detail>
                {r.rejection && (
                    <div style={{ margin: '0 0 14px', background: '#FEF2F2', borderRadius: 12, padding: '10px 12px' }}>
                        <div style={{ fontSize: 12, fontWeight: 700, color: '#B91C1C', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                            Reason
                        </div>
                        <p style={{ fontSize: 14, color: '#7F1D1D', margin: '4px 0 0' }}>{r.rejection.reason}</p>
                        {(r.rejection.rejected_by_name || r.rejection.rejected_at) && (
                            <p style={{ fontSize: 12, color: '#B91C1C', margin: '6px 0 0' }}>
                                {[r.rejection.rejected_by_name, r.rejection.rejected_at].filter(Boolean).join(' · ')}
                            </p>
                        )}
                    </div>
                )}
            </Card>

            {r.rfq_invitations.length > 0 && (
                <>
                    <SectionLabel zone="purchase">Suppliers ({r.rfq_invitations.length})</SectionLabel>
                    <Card>
                        {r.rfq_invitations.map((inv, i) => {
                            const status = INVITATION_STATUS[inv.status] ?? INVITATION_STATUS.pending;

                            return (
                                <ListRow
                                    key={inv.id}
                                    leading={<Avatar name={inv.supplier_name} size={36} radius={18} tone="slate" />}
                                    title={<span style={{ fontWeight: 500 }}>{inv.supplier_name}</span>}
                                    subtitle={inv.channel !== 'email' ? capitalise(inv.channel) : null}
                                    trailing={(
                                        <span style={{ display: 'flex', gap: 6, alignItems: 'center', flexShrink: 0 }}>
                                            {inv.whatsapp_link && (
                                                <a href={inv.whatsapp_link} target="_blank" rel="noreferrer" style={{ textDecoration: 'none' }}>
                                                    <Pill tone="green">WhatsApp</Pill>
                                                </a>
                                            )}
                                            <Pill tone={INVITATION_TONE[inv.status] ?? 'slate'}>
                                                {inv.status === 'sent' ? 'RFQ sent' : status.label}
                                            </Pill>
                                        </span>
                                    )}
                                    last={i === r.rfq_invitations.length - 1}
                                />
                            );
                        })}
                    </Card>
                </>
            )}

            {r.supplier_quotes.length > 0 && r.items.length > 0 && (
                <>
                    <SectionLabel zone="purchase">Items ({r.items.length})</SectionLabel>
                    <Card>
                        {r.items.map((item, i) => (
                            <ListRow
                                key={item.id}
                                title={item.description}
                                subtitle={item.quote_supplier_names.length ? item.quote_supplier_names.join(', ') : 'No quotes yet'}
                                trailing={item.is_awarded
                                    ? <Pill tone="green">✓ Awarded</Pill>
                                    : (
                                        <Pill tone={item.quote_count >= 2 ? 'blue' : 'slate'}>
                                            {item.quote_count} {item.quote_count === 1 ? 'quote' : 'quotes'}
                                        </Pill>
                                    )}
                                last={i === r.items.length - 1}
                            />
                        ))}
                    </Card>
                </>
            )}

            {r.supplier_quotes.length > 0 && (
                <>
                    <SectionLabel zone="purchase">Quotes ({r.supplier_quotes.length})</SectionLabel>
                    <Card>
                        {r.supplier_quotes.map((quote, i) => {
                            let note = null;
                            if (quote.has_awarded_items) note = <span style={{ fontSize: 12, fontWeight: 600, color: '#15803D' }}>{quote.awarded_item_count} item(s) awarded</span>;
                            else if (quote.is_lowest) note = <span style={{ fontSize: 12, fontWeight: 600, color: C.accent }}>Lowest</span>;

                            return (
                                <ListRow
                                    key={quote.id}
                                    to={r.permissions.manageQuotes ? `/app/purchase/requests/${r.id}/quotes` : undefined}
                                    title={quote.supplier_name}
                                    meta={note}
                                    trailing={(
                                        <span style={{
                                            fontWeight: 700, fontSize: 15, flexShrink: 0,
                                            color: quote.has_awarded_items ? '#15803D' : (quote.is_lowest ? C.accent : C.text),
                                        }}>
                                            {bd(quote.total_amount)}
                                        </span>
                                    )}
                                    last={i === r.supplier_quotes.length - 1}
                                />
                            );
                        })}
                    </Card>
                </>
            )}

            {r.purchase_orders.length > 0 && (
                <>
                    <SectionLabel zone="purchase">LPOs ({r.purchase_orders.length})</SectionLabel>
                    <Card>
                        {r.purchase_orders.map((po, i) => (
                            <ListRow
                                key={po.id}
                                to={`/app/purchase/orders/${po.id}`}
                                title={<DocNo size={14}>{po.po_number}</DocNo>}
                                subtitle={po.supplier_name ?? '—'}
                                trailing={(
                                    <span style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 4, flexShrink: 0 }}>
                                        <span style={{ fontWeight: 700, fontSize: 15 }}>{bd(po.total_amount)}</span>
                                        <Pill tone={PO_TONE[po.status] ?? 'slate'}>{(PO_STATUS[po.status] ?? PO_STATUS.draft).label}</Pill>
                                    </span>
                                )}
                                last={i === r.purchase_orders.length - 1}
                            />
                        ))}
                    </Card>
                </>
            )}
        </>
    );
}
