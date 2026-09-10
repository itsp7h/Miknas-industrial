import {
    ConfirmCodeBlock, DescriptionEditor, FormError, LogisticsFields, TermsBlock,
} from '../../../components/rfq/QuoteFields';
import useRfqPortal, { money, qty } from '../../../components/rfq/useRfqPortal';
import { ErrorScreen, ExpiredScreen, LoadingScreen, SubmittedScreen } from '../../../components/rfq/RfqStates';

const th = {
    background: '#f8fafc', padding: '11px 16px', textAlign: 'left', fontSize: 11,
    fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: '.03em',
};

const td = { padding: '12px 16px', borderBottom: '1px solid #f1f5f9', verticalAlign: 'middle' };

const foot = { padding: '13px 16px', fontWeight: 700, textAlign: 'right' };

/**
 * The desktop supplier portal: one priced row per line item, in a table.
 * The mobile counterpart stacks the same row into a card — a genuinely
 * different layout, which is why they are separate files (CLAUDE.md #12)
 * rather than one tree with a flag.
 */
export default function QuotePage({ token, load, send }) {
    const f = useRfqPortal({ token, load, send });

    if (f.state === 'loading') return <LoadingScreen />;
    if (f.state === 'error') return <ErrorScreen message={f.loadError} />;
    if (f.state === 'expired') return <ExpiredScreen invitation={f.invitation} />;
    if (f.state === 'submitted') return <SubmittedScreen invitation={f.invitation} />;

    const { invitation, items, rows, vatRate } = f;

    return (
        <div style={{ padding: '28px 16px', minHeight: '100vh' }}>
            <div style={{
                background: '#fff', borderRadius: 16, boxShadow: '0 4px 24px rgba(0,0,0,.08)',
                maxWidth: 980, margin: '0 auto', overflow: 'hidden',
            }}>
                <div style={{ background: 'linear-gradient(135deg,#2563eb,#1d4ed8)', padding: '28px 36px' }}>
                    <div style={{
                        fontSize: 11, fontWeight: 600, color: 'rgba(255,255,255,.7)',
                        textTransform: 'uppercase', letterSpacing: '.06em',
                    }}>
                        Request for Quotation
                    </div>
                    <div style={{ fontSize: 22, fontWeight: 700, color: '#fff', marginTop: 4 }}>
                        {invitation.request.request_number}
                    </div>
                    {invitation.request.project_name && (
                        <div style={{ fontSize: 13, color: 'rgba(255,255,255,.8)', marginTop: 2 }}>
                            {invitation.request.project_name}
                        </div>
                    )}
                </div>

                <div style={{ padding: 36 }}>
                    <p style={{ fontSize: 14, color: '#334155', margin: '0 0 6px' }}>
                        Hello <strong>{invitation.supplier_name}</strong>,
                    </p>
                    <p style={{ fontSize: 13, color: '#64748b', margin: '0 0 24px' }}>
                        Please enter your unit prices for the items below, then scroll down
                        and submit. This link is private to your company and can only be
                        submitted once.
                    </p>

                    <FormError message={f.formError} />

                    <form onSubmit={f.submit} noValidate>
                        <div style={{
                            overflowX: 'auto', marginBottom: 24,
                            border: '1px solid #e2e8f0', borderRadius: 10,
                        }}>
                            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
                                <thead>
                                    <tr>
                                        <th style={th}>#</th>
                                        <th style={th}>Description</th>
                                        <th style={th}>Qty</th>
                                        <th style={th}>Unit</th>
                                        <th style={{ ...th, textAlign: 'center' }}>N/A?</th>
                                        <th style={{ ...th, textAlign: 'center' }}>VAT?</th>
                                        <th style={{ ...th, textAlign: 'right' }}>Unit Price (BD)</th>
                                        <th style={{ ...th, textAlign: 'right' }}>Total (BD)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {items.map((item, index) => {
                                        const row = rows[item.id];
                                        const total = f.lineTotal(item);
                                        const editing = f.editing.id === item.id;

                                        return (
                                            <tr key={item.id} style={{ opacity: row.notAvailable ? 0.6 : 1 }}>
                                                <td style={{ ...td, color: '#94a3b8', fontSize: 12 }}>{index + 1}</td>
                                                <td style={td}>
                                                    <DescriptionEditor
                                                        original={item.description}
                                                        value={editing ? f.editing.draft : row.description}
                                                        editing={editing}
                                                        disabled={f.submitting}
                                                        onChange={f.setDraft}
                                                        onEdit={() => f.beginEdit(item)}
                                                        onDone={(save) => f.endEdit(item, save)}
                                                    />
                                                </td>
                                                <td style={td}>{qty(item.quantity_required)}</td>
                                                <td style={{ ...td, color: '#64748b' }}>{item.unit || '—'}</td>
                                                <td style={{ ...td, textAlign: 'center' }}>
                                                    <input
                                                        type="checkbox"
                                                        aria-label={`${item.description} is not available`}
                                                        checked={row.notAvailable}
                                                        disabled={f.submitting}
                                                        onChange={(e) => f.setNotAvailable(item.id, e.target.checked)}
                                                        style={{ width: 16, height: 16, accentColor: '#dc2626', cursor: 'pointer' }}
                                                    />
                                                </td>
                                                <td style={{ ...td, textAlign: 'center' }}>
                                                    {vatRate > 0 ? (
                                                        <input
                                                            type="checkbox"
                                                            aria-label={`Apply VAT to ${item.description}`}
                                                            checked={row.isVatable}
                                                            disabled={row.notAvailable || f.submitting}
                                                            onChange={(e) => f.setRow(item.id, { isVatable: e.target.checked })}
                                                            style={{
                                                                width: 16, height: 16, accentColor: '#2563eb',
                                                                cursor: row.notAvailable ? 'not-allowed' : 'pointer',
                                                                opacity: row.notAvailable ? 0.35 : 1,
                                                            }}
                                                        />
                                                    ) : (
                                                        <span style={{ color: '#cbd5e1', fontSize: 11 }}>—</span>
                                                    )}
                                                </td>
                                                <td style={{ ...td, textAlign: 'right' }}>
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        step="0.001"
                                                        placeholder="0.000"
                                                        aria-label={`Unit price for ${item.description}`}
                                                        value={row.unitPrice}
                                                        disabled={row.notAvailable || f.submitting}
                                                        onChange={(e) => f.setRow(item.id, { unitPrice: e.target.value })}
                                                        style={{
                                                            width: 140, textAlign: 'right', padding: '9px 12px',
                                                            border: '1.5px solid #e2e8f0', borderRadius: 8,
                                                            fontSize: 13, outline: 'none', background: '#fff',
                                                            fontFamily: 'inherit',
                                                            opacity: row.notAvailable ? 0.35 : 1,
                                                        }}
                                                    />
                                                </td>
                                                <td style={{ ...td, textAlign: 'right', fontWeight: 600 }}>
                                                    {row.notAvailable ? (
                                                        <span style={{
                                                            fontSize: 11, fontWeight: 700, color: '#dc2626',
                                                            background: '#fef2f2', padding: '2px 7px', borderRadius: 4,
                                                        }}>
                                                            Not available
                                                        </span>
                                                    ) : (total > 0 ? money(total) : '—')}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                                <tfoot>
                                    <tr style={{ background: '#f8fafc' }}>
                                        <td colSpan={7} style={{ ...foot, fontSize: 13, color: '#475569', fontWeight: 400 }}>
                                            Subtotal:
                                        </td>
                                        <td style={{ ...foot, fontSize: 14, color: '#475569' }}>{money(f.totals.subtotal)}</td>
                                    </tr>
                                    {vatRate > 0 && (
                                        <tr style={{ background: '#eef2fb' }}>
                                            <td colSpan={7} style={{ ...foot, fontSize: 13, color: '#3b5ea6', fontWeight: 400 }}>
                                                VAT ({qty(vatRate)}%):
                                            </td>
                                            <td style={{ ...foot, fontSize: 14, color: '#3b5ea6' }}>{money(f.totals.vat)}</td>
                                        </tr>
                                    )}
                                    <tr style={{ background: '#f8fafc', borderTop: '2px solid #e2e8f0' }}>
                                        <td colSpan={7} style={{ ...foot, fontSize: 13, color: '#475569' }}>Grand Total:</td>
                                        <td style={{ ...foot, fontSize: 15, color: '#2563eb' }}>{money(f.totals.grand)}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <LogisticsFields compact={false} meta={f.meta} setField={f.setField} disabled={f.submitting} />

                        <TermsBlock
                            accepted={f.terms}
                            onChange={f.setTerms}
                            error={f.errors.terms}
                            disabled={f.submitting}
                        />

                        <ConfirmCodeBlock
                            compact={false}
                            code={f.confirmCode}
                            value={f.confirmInput}
                            onChange={f.setConfirmInput}
                            matches={f.codeMatches}
                            error={f.errors.confirm_code}
                            disabled={f.submitting}
                        />

                        <button
                            type="submit"
                            disabled={!f.canSubmit}
                            style={{
                                width: '100%', padding: 15, border: 'none', borderRadius: 10,
                                background: 'linear-gradient(135deg,#2563eb,#1d4ed8)', color: '#fff',
                                fontSize: 15, fontWeight: 700,
                                opacity: f.canSubmit ? 1 : 0.4,
                                cursor: f.canSubmit ? 'pointer' : 'not-allowed',
                            }}
                        >
                            {f.submitting ? 'Submitting…' : 'Submit My Quote →'}
                        </button>

                        {/* The Blade form left a disabled button unexplained —
                            the supplier could see it was dead but not why. */}
                        {f.blockedReason && !f.submitting && (
                            <p style={{ fontSize: 12, color: '#94a3b8', marginTop: 10, textAlign: 'center' }}>
                                {f.blockedReason}
                            </p>
                        )}
                    </form>

                    <p style={{ fontSize: 11, color: '#cbd5e1', marginTop: 18, textAlign: 'center' }}>
                        {invitation.expires_at_text
                            ? `Link expires ${invitation.expires_at_text} · One submission only`
                            : 'One submission only'}
                    </p>
                </div>
            </div>
        </div>
    );
}
