/**
 * The parts of the quote form whose *content* must not drift between the
 * desktop table and the mobile cards: the terms a supplier is agreeing to,
 * the confirmation-code gate, and the logistics fields. Layout stays with
 * each page (CLAUDE.md #12) — only the wording and the semantics are shared.
 *
 * The two Blade pages had already drifted: mobile said "Prices are valid for
 * 30 days from submission" where desktop said "Prices stated in this quote
 * are valid for 30 days from the submission date", and mobile had quietly
 * dropped "and constitutes a formal offer". These are contract terms.
 */

const TERMS = [
    <>Prices stated in this quote are valid for <strong>30 days</strong> from the submission date.</>,
    <>Delivery will be made within the specified delivery time stated above.</>,
    <>All items supplied will meet the required specifications and quality standards.</>,
    <>This quote is <strong>binding upon acceptance</strong> and constitutes a formal offer.</>,
];

export function FormError({ message }) {
    if (!message) return null;

    return (
        <div
            role="alert"
            style={{
                marginBottom: 16, padding: '10px 12px', borderRadius: 8,
                background: '#fef2f2', border: '1px solid #fecaca',
                color: '#b91c1c', fontSize: 13,
            }}
        >
            {message}
        </div>
    );
}

export function FieldLabel({ children, htmlFor, color = '#64748b' }) {
    return (
        <label
            htmlFor={htmlFor}
            style={{
                display: 'block', fontSize: 11, fontWeight: 700, color,
                textTransform: 'uppercase', letterSpacing: '.05em', marginBottom: 5,
            }}
        >
            {children}
        </label>
    );
}

const inputStyle = {
    width: '100%', padding: '9px 12px', border: '1.5px solid #e2e8f0',
    borderRadius: 8, fontSize: 13, outline: 'none', background: '#fff',
    fontFamily: 'inherit',
};

export function LogisticsFields({ compact, meta, setField, disabled }) {
    return (
        <>
            <div style={{
                display: 'grid',
                gridTemplateColumns: compact ? '1fr' : '1fr 1fr',
                gap: 16, marginBottom: 16,
            }}>
                <div>
                    <FieldLabel htmlFor="lead_time_days">Delivery Time (days)</FieldLabel>
                    <input
                        id="lead_time_days"
                        type="number"
                        min="0"
                        placeholder="e.g. 14"
                        disabled={disabled}
                        style={inputStyle}
                        value={meta.lead_time_days}
                        onChange={(e) => setField('lead_time_days', e.target.value)}
                    />
                </div>
                <div>
                    <FieldLabel htmlFor="payment_terms">Payment Terms</FieldLabel>
                    <input
                        id="payment_terms"
                        type="text"
                        placeholder="e.g. 30 days net"
                        disabled={disabled}
                        style={inputStyle}
                        value={meta.payment_terms}
                        onChange={(e) => setField('payment_terms', e.target.value)}
                    />
                </div>
            </div>
            <div style={{ marginBottom: 20 }}>
                <FieldLabel htmlFor="notes">Notes / Remarks</FieldLabel>
                <textarea
                    id="notes"
                    rows={3}
                    placeholder="Any additional notes, conditions, or remarks…"
                    disabled={disabled}
                    style={{ ...inputStyle, resize: 'vertical' }}
                    value={meta.notes}
                    onChange={(e) => setField('notes', e.target.value)}
                />
            </div>
        </>
    );
}

export function TermsBlock({ accepted, onChange, error, disabled }) {
    return (
        <div style={{
            background: '#f8fafc', border: '1.5px solid #e2e8f0', borderRadius: 10,
            padding: '16px 18px', marginBottom: 16,
        }}>
            <div style={{
                fontSize: 11, fontWeight: 700, color: '#64748b', textTransform: 'uppercase',
                letterSpacing: '.06em', marginBottom: 10,
            }}>
                Terms &amp; Conditions
            </div>
            <ul style={{
                listStyle: 'none', padding: 0, margin: '0 0 14px',
                display: 'flex', flexDirection: 'column', gap: 7,
            }}>
                {TERMS.map((term, index) => (
                    <li
                        key={index}
                        style={{
                            display: 'flex', alignItems: 'flex-start', gap: 9,
                            fontSize: 12, color: '#475569', lineHeight: 1.5,
                        }}
                    >
                        <span style={{
                            flexShrink: 0, width: 18, height: 18, borderRadius: '50%',
                            background: '#dbeafe', display: 'flex', alignItems: 'center',
                            justifyContent: 'center', marginTop: 1,
                        }}>
                            <svg width="9" height="9" viewBox="0 0 10 10" fill="none" aria-hidden="true">
                                <path d="M2 5.5L4 7.5L8 3" stroke="#2563eb" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        </span>
                        {term}
                    </li>
                ))}
            </ul>

            <label
                htmlFor="terms-cb"
                style={{
                    display: 'flex', alignItems: 'flex-start', gap: 10, cursor: 'pointer',
                    padding: '10px 12px', background: '#fff',
                    border: '1.5px solid #e2e8f0', borderRadius: 8,
                }}
            >
                <input
                    id="terms-cb"
                    type="checkbox"
                    checked={accepted}
                    disabled={disabled}
                    onChange={(e) => onChange(e.target.checked)}
                    style={{ width: 16, height: 16, marginTop: 1, accentColor: '#2563eb', flexShrink: 0, cursor: 'pointer' }}
                />
                <span style={{ fontSize: 13, fontWeight: 600, color: '#0f172a' }}>
                    I have read and agree to the terms and conditions above
                </span>
            </label>

            {error && <div style={{ fontSize: 12, color: '#dc2626', marginTop: 6 }}>{error}</div>}
        </div>
    );
}

export function ConfirmCodeBlock({ compact, code, value, onChange, matches, error, disabled }) {
    const borderColor = value.length === 0 ? '#fde68a' : (matches ? '#16a34a' : '#ef4444');

    return (
        <div style={{
            background: '#fffbeb', border: '1.5px solid #fde68a', borderRadius: 10,
            padding: '16px 18px', marginBottom: 20,
        }}>
            <div style={{
                fontSize: 11, fontWeight: 700, color: '#92400e', textTransform: 'uppercase',
                letterSpacing: '.05em', marginBottom: 10, textAlign: compact ? 'center' : 'left',
            }}>
                Confirmation Required
            </div>

            <div style={{
                display: 'flex', gap: 14,
                flexDirection: compact ? 'column' : 'row',
                alignItems: compact ? 'stretch' : 'center',
            }}>
                <div style={{ flexShrink: 0, textAlign: compact ? 'center' : 'left' }}>
                    <div style={{ fontSize: 11, color: '#92400e', marginBottom: 6 }}>Copy this code:</div>
                    <div style={{
                        fontSize: 24, fontWeight: 800, letterSpacing: '.2em', color: '#92400e',
                        background: '#fef3c7', border: '2px dashed #f59e0b', borderRadius: 8,
                        padding: '10px 20px', fontFamily: 'ui-monospace, SFMono-Regular, Menlo, monospace',
                        userSelect: 'all', whiteSpace: 'nowrap',
                    }}>
                        {code}
                    </div>
                </div>

                <div style={{ flex: 1, minWidth: 0 }}>
                    <FieldLabel htmlFor="confirm-input" color="#92400e">Paste code here</FieldLabel>
                    <input
                        id="confirm-input"
                        type="text"
                        autoComplete="off"
                        autoCorrect="off"
                        autoCapitalize="characters"
                        spellCheck="false"
                        placeholder="Paste the code above"
                        disabled={disabled}
                        value={value}
                        onChange={(e) => onChange(e.target.value)}
                        style={{
                            ...inputStyle,
                            padding: '11px 12px', borderColor,
                            fontSize: 16, fontWeight: 700, letterSpacing: '.12em',
                            fontFamily: 'ui-monospace, SFMono-Regular, Menlo, monospace',
                            textTransform: 'uppercase',
                            textAlign: compact ? 'center' : 'left',
                        }}
                    />
                    {error && <div style={{ fontSize: 12, color: '#dc2626', marginTop: 4 }}>{error}</div>}
                </div>
            </div>
        </div>
    );
}

/**
 * The inline "✎ Edit" affordance on an item's description. A supplier may be
 * quoting a near-equivalent product, and the buyer needs to see that they
 * did — so an edited line is badged rather than silently substituted.
 */
export function DescriptionEditor({ original, value, onChange, disabled, editing, onEdit, onDone, compact }) {
    const adjusted = value.trim() !== original;

    if (editing) {
        return (
            <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                <input
                    type="text"
                    autoFocus
                    aria-label="Item description"
                    disabled={disabled}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') { e.preventDefault(); onDone(true); }
                        if (e.key === 'Escape') onDone(false);
                    }}
                    style={{
                        flex: 1, minWidth: 0, padding: '5px 8px', border: '1.5px solid #2563eb',
                        borderRadius: 6, fontSize: compact ? 15 : 13, fontWeight: 500,
                        outline: 'none', fontFamily: 'inherit',
                    }}
                />
                <button
                    type="button"
                    onClick={() => onDone(true)}
                    aria-label="Save item description"
                    style={{
                        flexShrink: 0, background: '#2563eb', color: '#fff', border: 'none',
                        borderRadius: 5, padding: '4px 8px', cursor: 'pointer', fontSize: 12, fontWeight: 700,
                    }}
                >
                    ✓
                </button>
                <button
                    type="button"
                    onClick={() => onDone(false)}
                    aria-label="Cancel editing item description"
                    style={{
                        flexShrink: 0, background: '#f1f5f9', color: '#64748b',
                        border: '1px solid #e2e8f0', borderRadius: 5, padding: '4px 8px',
                        cursor: 'pointer', fontSize: 12,
                    }}
                >
                    ✕
                </button>
            </div>
        );
    }

    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
            <span style={{ fontWeight: compact ? 700 : 500, fontSize: compact ? 17 : 13, color: '#0f172a' }}>
                {value}
            </span>
            <button
                type="button"
                onClick={onEdit}
                disabled={disabled}
                title="Edit item name"
                style={{
                    flexShrink: 0, background: 'none', border: '1px solid #e2e8f0', borderRadius: 5,
                    padding: '2px 6px', cursor: 'pointer', color: '#64748b', fontSize: 11, lineHeight: 1.4,
                }}
            >
                ✎ Edit
            </button>
            {adjusted && (
                <span style={{
                    fontSize: 10, fontWeight: 700, background: '#fef3c7', color: '#92400e',
                    padding: '1px 6px', borderRadius: 4, border: '1px solid #fde68a',
                }}>
                    adjusted
                </span>
            )}
        </div>
    );
}
