import {
    ConfirmCodeBlock, DescriptionEditor, FormError, LogisticsFields, TermsBlock,
} from '../../../components/rfq/QuoteFields';
import useRfqPortal, { money, qty } from '../../../components/rfq/useRfqPortal';
import { ErrorScreen, ExpiredScreen, LoadingScreen, SubmittedScreen } from '../../../components/rfq/RfqStates';

const sectionLabel = {
    fontSize: 12, fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase',
    letterSpacing: '.05em', margin: '24px 0 12px',
};

const card = {
    background: '#fff', border: '1px solid #e2e8f0', borderRadius: 16,
    padding: 16, marginBottom: 16, boxShadow: '0 1px 2px rgba(15,23,42,.04)',
};

/**
 * The mobile supplier portal: each line item is a card with switch-style
 * toggles and a large price field, and the submit sits in a bar pinned above
 * the home indicator — a phone cannot usefully render the desktop table.
 *
 * The Blade version of this page pulled Bootstrap 5 off a CDN for those
 * controls. A supplier-facing page on a bad connection should not wait on a
 * third-party stylesheet to become legible, so the toggles are drawn here.
 */
export default function QuotePage({ token, load, send }) {
    const f = useRfqPortal({ token, load, send });

    if (f.state === 'loading') return <LoadingScreen compact />;
    if (f.state === 'error') return <ErrorScreen compact message={f.loadError} />;
    if (f.state === 'expired') return <ExpiredScreen compact invitation={f.invitation} />;
    if (f.state === 'submitted') return <SubmittedScreen compact invitation={f.invitation} />;

    const { invitation, items, rows, vatRate } = f;

    return (
        <div style={{ background: '#f8fafc', minHeight: '100vh', paddingBottom: 110 }}>
            <div style={{
                background: 'linear-gradient(135deg,#2563eb,#1d4ed8)',
                padding: '22px 18px', color: '#fff',
            }}>
                <div style={{
                    fontSize: 11, fontWeight: 600, color: 'rgba(255,255,255,.7)',
                    textTransform: 'uppercase', letterSpacing: '.06em',
                }}>
                    Request for Quotation
                </div>
                <div style={{ fontSize: 22, fontWeight: 700, marginTop: 4 }}>
                    {invitation.request.request_number}
                </div>
                {invitation.request.project_name && (
                    <div style={{ fontSize: 13, color: 'rgba(255,255,255,.8)', marginTop: 2 }}>
                        {invitation.request.project_name}
                    </div>
                )}
            </div>

            <div style={{ padding: '18px 16px 0' }}>
                <h1 style={{ fontSize: 19, fontWeight: 700, color: '#0f172a', margin: '0 0 6px' }}>
                    Hello, {invitation.supplier_name}
                </h1>
                <p style={{ fontSize: 13, color: '#64748b', lineHeight: 1.6, margin: 0 }}>
                    Please enter your unit prices below. This link is private to your
                    company and can only be submitted once.
                </p>

                <div style={{ marginTop: 16 }}>
                    <FormError message={f.formError} />
                </div>

                <form onSubmit={f.submit} noValidate>
                    <div style={sectionLabel}>Requested Items</div>

                    {items.map((item, index) => {
                        const row = rows[item.id];
                        const total = f.lineTotal(item);
                        const editing = f.editing.id === item.id;

                        return (
                            <div key={item.id} style={{ ...card, opacity: row.notAvailable ? 0.55 : 1 }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 6 }}>
                                    <span style={{
                                        fontSize: 11, fontWeight: 600, color: '#64748b', background: '#f1f5f9',
                                        border: '1px solid #e2e8f0', borderRadius: 999, padding: '2px 8px',
                                    }}>
                                        Item #{index + 1}
                                    </span>
                                    <span style={{ fontSize: 12, fontWeight: 700, color: '#2563eb' }}>
                                        {qty(item.quantity_required)} {item.unit || ''}
                                    </span>
                                </div>

                                <DescriptionEditor
                                    compact
                                    original={item.description}
                                    value={editing ? f.editing.draft : row.description}
                                    editing={editing}
                                    disabled={f.submitting}
                                    onChange={f.setDraft}
                                    onEdit={() => f.beginEdit(item)}
                                    onDone={(save) => f.endEdit(item, save)}
                                />

                                <div style={{ marginTop: 14 }}>
                                    <Toggle
                                        id={`na-${item.id}`}
                                        label="Item not available"
                                        // Same accessible name as the desktop
                                        // checkbox: one test drives both trees.
                                        ariaLabel={`${item.description} is not available`}
                                        checked={row.notAvailable}
                                        disabled={f.submitting}
                                        accent="#dc2626"
                                        onChange={(checked) => f.setNotAvailable(item.id, checked)}
                                    />
                                    {vatRate > 0 && (
                                        <Toggle
                                            id={`vat-${item.id}`}
                                            label={`Apply ${qty(vatRate)}% VAT`}
                                            ariaLabel={`Apply VAT to ${item.description}`}
                                            checked={row.isVatable}
                                            disabled={row.notAvailable || f.submitting}
                                            accent="#2563eb"
                                            onChange={(checked) => f.setRow(item.id, { isVatable: checked })}
                                        />
                                    )}
                                </div>

                                <label
                                    htmlFor={`price-${item.id}`}
                                    style={{
                                        display: 'block', fontSize: 11, fontWeight: 700, color: '#64748b',
                                        textTransform: 'uppercase', letterSpacing: '.05em', margin: '14px 0 5px',
                                    }}
                                >
                                    Unit Price (BD)
                                </label>
                                <input
                                    id={`price-${item.id}`}
                                    type="number"
                                    inputMode="decimal"
                                    min="0"
                                    step="0.001"
                                    placeholder="0.000"
                                    aria-label={`Unit price for ${item.description}`}
                                    value={row.unitPrice}
                                    disabled={row.notAvailable || f.submitting}
                                    onChange={(e) => f.setRow(item.id, { unitPrice: e.target.value })}
                                    style={{
                                        width: '100%', padding: '14px 14px', borderRadius: 12,
                                        border: '1.5px solid #e2e8f0', background: '#f8fafc',
                                        fontSize: 22, fontWeight: 700, color: '#2563eb',
                                        outline: 'none', fontFamily: 'inherit',
                                    }}
                                />

                                <div style={{
                                    display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                                    background: '#f8fafc', borderRadius: 12, padding: '12px 14px', marginTop: 12,
                                }}>
                                    <span style={{ fontSize: 12, fontWeight: 600, color: '#64748b' }}>Item Total</span>
                                    {row.notAvailable ? (
                                        <span style={{
                                            fontSize: 12, fontWeight: 700, color: '#dc2626',
                                            background: '#fef2f2', padding: '3px 9px', borderRadius: 6,
                                        }}>
                                            Not available
                                        </span>
                                    ) : (
                                        <span style={{ fontSize: 17, fontWeight: 800, color: '#0f172a' }}>
                                            {total > 0 ? money(total) : '—'}
                                        </span>
                                    )}
                                </div>
                            </div>
                        );
                    })}

                    <div style={sectionLabel}>Summary</div>
                    <div style={{
                        background: '#0f172a', color: '#fff', borderRadius: 16,
                        padding: 18, marginBottom: 16,
                    }}>
                        <SummaryRow label="Subtotal" value={money(f.totals.subtotal)} tone="rgba(255,255,255,.65)" />
                        {vatRate > 0 && (
                            <SummaryRow label={`VAT (${qty(vatRate)}%)`} value={money(f.totals.vat)} tone="#7dd3fc" />
                        )}
                        <div style={{ borderTop: '1px solid rgba(255,255,255,.15)', margin: '12px 0 10px' }} />
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end' }}>
                            <span style={{ fontSize: 13, fontWeight: 500, color: 'rgba(255,255,255,.65)' }}>
                                Grand Total
                            </span>
                            <span style={{ fontSize: 24, fontWeight: 800 }}>{money(f.totals.grand)}</span>
                        </div>
                    </div>

                    <div style={sectionLabel}>Logistics &amp; Terms</div>
                    <div style={card}>
                        <LogisticsFields compact meta={f.meta} setField={f.setField} disabled={f.submitting} />
                    </div>

                    <div style={sectionLabel}>Agreement</div>
                    <TermsBlock
                        accepted={f.terms}
                        onChange={f.setTerms}
                        error={f.errors.terms}
                        disabled={f.submitting}
                    />

                    <ConfirmCodeBlock
                        compact
                        code={f.confirmCode}
                        value={f.confirmInput}
                        onChange={f.setConfirmInput}
                        matches={f.codeMatches}
                        error={f.errors.confirm_code}
                        disabled={f.submitting}
                    />

                    <p style={{
                        fontSize: 12, color: '#94a3b8', textAlign: 'center',
                        lineHeight: 1.6, margin: '0 0 8px',
                    }}>
                        {invitation.expires_at_text && <>Link expires {invitation.expires_at_text}<br /></>}
                        One submission only
                    </p>

                    <div style={{
                        position: 'fixed', left: 0, right: 0, bottom: 0, zIndex: 30,
                        background: 'rgba(255,255,255,.94)', backdropFilter: 'blur(10px)',
                        WebkitBackdropFilter: 'blur(10px)', borderTop: '1px solid #e2e8f0',
                        padding: '12px 16px calc(12px + env(safe-area-inset-bottom))',
                    }}>
                        {f.blockedReason && !f.submitting && (
                            <p style={{ fontSize: 12, color: '#94a3b8', margin: '0 0 8px', textAlign: 'center' }}>
                                {f.blockedReason}
                            </p>
                        )}
                        <button
                            type="submit"
                            disabled={!f.canSubmit}
                            style={{
                                width: '100%', padding: '16px', border: 'none', borderRadius: 999,
                                background: 'linear-gradient(135deg,#2563eb,#1d4ed8)', color: '#fff',
                                fontSize: 16, fontWeight: 700,
                                opacity: f.canSubmit ? 1 : 0.45,
                                cursor: f.canSubmit ? 'pointer' : 'not-allowed',
                            }}
                        >
                            {f.submitting ? 'Submitting…' : 'Submit Quotation'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function SummaryRow({ label, value, tone }) {
    return (
        <div style={{
            display: 'flex', justifyContent: 'space-between',
            fontSize: 13, fontWeight: 500, color: tone, marginBottom: 6,
        }}>
            <span>{label}</span>
            <span>{value}</span>
        </div>
    );
}

/** A switch, drawn rather than borrowed — see the note at the top of the file. */
function Toggle({ id, label, ariaLabel, checked, disabled, accent, onChange }) {
    return (
        <label
            htmlFor={id}
            style={{
                display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                gap: 12, padding: '11px 0', borderTop: '1px solid #f1f5f9',
                cursor: disabled ? 'not-allowed' : 'pointer',
                opacity: disabled ? 0.5 : 1,
            }}
        >
            <span style={{ fontSize: 14, fontWeight: 500, color: '#0f172a' }}>{label}</span>
            <span style={{
                position: 'relative', flexShrink: 0, width: 46, height: 28, borderRadius: 999,
                background: checked ? accent : '#cbd5e1', transition: 'background .15s ease',
            }}>
                <input
                    id={id}
                    type="checkbox"
                    aria-label={ariaLabel}
                    checked={checked}
                    disabled={disabled}
                    onChange={(e) => onChange(e.target.checked)}
                    style={{
                        position: 'absolute', inset: 0, width: '100%', height: '100%',
                        opacity: 0, margin: 0, cursor: 'inherit',
                    }}
                />
                <span style={{
                    position: 'absolute', top: 3, left: checked ? 21 : 3,
                    width: 22, height: 22, borderRadius: '50%', background: '#fff',
                    boxShadow: '0 1px 3px rgba(15,23,42,.3)', transition: 'left .15s ease',
                }} />
            </span>
        </label>
    );
}
