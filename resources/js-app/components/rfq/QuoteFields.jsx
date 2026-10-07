import { useEffect, useId, useRef, useState } from 'react';

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

/**
 * What each field asks for, in the supplier's words. Shared like TERMS so the
 * desktop table and the mobile cards explain a field the same way.
 */
export const TIPS = {
    reference: 'Your own quotation number. It is printed on our purchase order (LPO) under your company name.',
    qty: 'The quantity we need, in the unit shown.',
    unit: 'The unit we asked for. If you sell it another way, pick your unit and give your quantity in it.',
    supplierQty: 'How many of your units you will supply in total. Your unit price is per one of these.',
    notAvailable: 'Tick if you cannot supply this item. It then needs no price.',
    vat: (rate) => `Tick if VAT applies to this item. ${rate}% is added on top of its total.`,
    unitPrice: 'Your price for one unit, in Bahraini Dinar, before VAT. Up to 3 decimals.',
    leadTime: 'How many days after receiving our purchase order you can deliver.',
    paymentTerms: 'When and how you expect to be paid, e.g. 30 days net or cash on delivery.',
    notes: 'Anything else we should know: brand, origin, warranty, conditions.',
    document: 'Optional. Your own quotation on your letterhead, if you have one: PDF, JPG or PNG, up to 10 MB. The prices you enter above are what we compare.',
    terms: 'You must accept these terms before the quote can be submitted.',
    confirmCode: 'Copy the code shown here into the box. It confirms a person is submitting this quote.',
};

/**
 * A small ⓘ beside a field that explains it. Hover shows it on a desktop; a
 * tap pins it on a phone, where `title` never appears, and a tap anywhere
 * else or Escape closes it. Kept outside the field's <label> where it can be,
 * so the label still names the field and nothing else.
 *
 * `placement` is "bottom" inside the items table, whose horizontal scroll
 * would clip a popover above the header row; `align` keeps one at the right
 * edge from running off the table.
 */
export function InfoTip({ text, label, placement = 'top', align = 'left' }) {
    const id = useId();
    const ref = useRef(null);
    const [hover, setHover] = useState(false);
    const [pinned, setPinned] = useState(false);
    const open = hover || pinned;

    useEffect(() => {
        if (!pinned) return undefined;
        const close = (e) => { if (!ref.current?.contains(e.target)) setPinned(false); };
        const escape = (e) => { if (e.key === 'Escape') setPinned(false); };
        document.addEventListener('pointerdown', close);
        document.addEventListener('keydown', escape);

        return () => {
            document.removeEventListener('pointerdown', close);
            document.removeEventListener('keydown', escape);
        };
    }, [pinned]);

    const horizontal = align === 'right'
        ? { right: 0 }
        : align === 'center' ? { left: '50%', transform: 'translateX(-50%)' } : { left: 0 };

    return (
        <span
            ref={ref}
            style={{ position: 'relative', display: 'inline-flex', verticalAlign: 'middle', marginLeft: 5 }}
            onPointerEnter={(e) => { if (e.pointerType === 'mouse') setHover(true); }}
            onPointerLeave={(e) => { if (e.pointerType === 'mouse') setHover(false); }}
        >
            <button
                type="button"
                aria-label={`What is ${label}?`}
                aria-expanded={open}
                aria-describedby={open ? id : undefined}
                onClick={(e) => { e.preventDefault(); e.stopPropagation(); setPinned((p) => !p); }}
                onFocus={() => setHover(true)}
                onBlur={() => setHover(false)}
                style={{
                    display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                    width: 15, height: 15, padding: 0, border: 'none', borderRadius: '50%',
                    background: open ? '#2563eb' : '#cbd5e1', color: '#fff', cursor: 'help',
                    flexShrink: 0,
                }}
            >
                <svg width="9" height="9" viewBox="0 0 10 10" aria-hidden="true">
                    <circle cx="5" cy="2" r="1.1" fill="currentColor" />
                    <rect x="4.1" y="4" width="1.8" height="5" rx=".9" fill="currentColor" />
                </svg>
            </button>
            {open && (
                <span
                    id={id}
                    role="tooltip"
                    style={{
                        position: 'absolute', zIndex: 30, ...horizontal,
                        ...(placement === 'bottom' ? { top: 'calc(100% + 6px)' } : { bottom: 'calc(100% + 6px)' }),
                        width: 'max-content', maxWidth: 'min(240px, 70vw)',
                        padding: '8px 10px', borderRadius: 8, background: '#0f172a', color: '#fff',
                        fontSize: 12, fontWeight: 400, lineHeight: 1.45, textAlign: 'left',
                        textTransform: 'none', letterSpacing: 'normal', whiteSpace: 'normal',
                        boxShadow: '0 6px 20px rgba(15,23,42,.25)',
                    }}
                >
                    {text}
                </span>
            )}
        </span>
    );
}

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

export function FieldLabel({ children, htmlFor, color = '#64748b', tip, tipLabel }) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', marginBottom: 5 }}>
            <label
                htmlFor={htmlFor}
                style={{
                    display: 'block', fontSize: 11, fontWeight: 700, color,
                    textTransform: 'uppercase', letterSpacing: '.05em',
                }}
            >
                {children}
            </label>
            {tip && <InfoTip text={tip} label={tipLabel || children} />}
        </div>
    );
}

/** The red asterisk beside every field the portal will not submit without. */
export function RequiredMark() {
    return (
        <span aria-hidden="true" style={{ color: '#dc2626', marginLeft: 3, fontWeight: 700 }}>*</span>
    );
}

/** The outline every missing required field takes once a submit has found it. */
export const MISSING = { borderColor: '#ef4444', background: '#fef2f2', boxShadow: '0 0 0 3px rgba(239,68,68,.15)' };

/** Spread onto a required input: the red outline plus what tells a screen reader. */
export const missingProps = (missing) => (missing
    ? { 'aria-invalid': true, 'data-missing': 'true' }
    : {});

const inputStyle = {
    width: '100%', padding: '9px 12px', border: '1.5px solid #e2e8f0',
    borderRadius: 8, fontSize: 13, outline: 'none', background: '#fff',
    fontFamily: 'inherit',
};

/**
 * The supplier's own quotation number, first on the form, under the opening
 * line: "Ref:" with a small box beside it, as the LPO prints it. Required —
 * the LPO shows it under the vendor's name.
 */
export function ReferenceField({ meta, setField, disabled, errors = {}, missing = false }) {
    const invalid = !!errors.reference || missing;

    return (
        <div style={{ marginBottom: 20 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <label htmlFor="reference" style={{ fontSize: 14, fontWeight: 700, color: '#0f172a', flexShrink: 0 }}>
                    Ref:<RequiredMark />
                </label>
                <InfoTip text={TIPS.reference} label="Ref" />
                <input
                    id="reference"
                    type="text"
                    required
                    maxLength={100}
                    placeholder="Quotation no."
                    disabled={disabled}
                    aria-invalid={invalid ? true : undefined}
                    data-missing={missing ? 'true' : undefined}
                    style={{
                        ...inputStyle,
                        width: 220, maxWidth: '100%', minWidth: 0, padding: '6px 10px',
                        ...(errors.reference ? { borderColor: '#ef4444' } : {}),
                        ...(missing ? MISSING : {}),
                    }}
                    value={meta.reference}
                    onChange={(e) => setField('reference', e.target.value)}
                />
            </div>
            {(errors.reference || missing) && (
                <div style={{ fontSize: 12, color: '#dc2626', marginTop: 4 }}>
                    {errors.reference || 'Please enter your quotation reference number.'}
                </div>
            )}
        </div>
    );
}

export function LogisticsFields({ compact, meta, setField, disabled }) {
    return (
        <>
            <div style={{
                display: 'grid',
                gridTemplateColumns: compact ? '1fr' : '1fr 1fr',
                gap: 16, marginBottom: 16,
            }}>
                <div>
                    <FieldLabel htmlFor="lead_time_days" tip={TIPS.leadTime}>Delivery Time (days)</FieldLabel>
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
                    <FieldLabel htmlFor="payment_terms" tip={TIPS.paymentTerms}>Payment Terms</FieldLabel>
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
                <FieldLabel htmlFor="notes" tip={TIPS.notes}>Notes / Remarks</FieldLabel>
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

/** "1.2 MB", "340 KB": enough to tell a scan from a one-page PDF. */
const fileSize = (bytes) => (bytes >= 1024 * 1024
    ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
    : `${Math.max(1, Math.round(bytes / 1024))} KB`);

/**
 * Their own quotation document, optional: one PDF or image beside the
 * figures typed above. A chosen file shows with a Remove, so a wrong pick is
 * undone without reloading the form.
 */
export function QuotationDocumentField({ file, onChange, error, disabled, accept }) {
    return (
        <div style={{ marginBottom: 20 }}>
            <FieldLabel htmlFor="quote-document" tip={TIPS.document} tipLabel="Your quotation">
                Your Quotation (optional)
            </FieldLabel>

            {file ? (
                <div style={{
                    display: 'flex', alignItems: 'center', gap: 10, padding: '9px 12px',
                    border: '1.5px solid #bbf7d0', background: '#f0fdf4', borderRadius: 8, fontSize: 13,
                }}>
                    <span aria-hidden="true">📎</span>
                    <span style={{ flex: 1, minWidth: 0, overflowWrap: 'anywhere', color: '#0f172a', fontWeight: 600 }}>
                        {file.name}
                        <span style={{ color: '#64748b', fontWeight: 400 }}> · {fileSize(file.size)}</span>
                    </span>
                    <button
                        type="button"
                        disabled={disabled}
                        onClick={() => onChange(null)}
                        style={{
                            flexShrink: 0, background: '#fff', color: '#64748b', border: '1px solid #e2e8f0',
                            borderRadius: 6, padding: '3px 10px', fontSize: 12, cursor: disabled ? 'not-allowed' : 'pointer',
                        }}
                    >
                        Remove
                    </button>
                </div>
            ) : (
                <input
                    id="quote-document"
                    type="file"
                    accept={accept}
                    disabled={disabled}
                    aria-invalid={error ? true : undefined}
                    onChange={(e) => {
                        const chosen = e.target.files?.[0] ?? null;
                        if (!onChange(chosen)) e.target.value = '';
                    }}
                    style={{
                        ...inputStyle, padding: '7px 10px',
                        ...(error ? { borderColor: '#ef4444' } : {}),
                    }}
                />
            )}

            <div style={{ fontSize: 11, color: error ? '#dc2626' : '#94a3b8', marginTop: 4 }}>
                {error || 'PDF, JPG or PNG, up to 10 MB.'}
            </div>
        </div>
    );
}

export function TermsBlock({ accepted, onChange, error, disabled, missing = false }) {
    return (
        <div style={{
            background: '#f8fafc', border: '1.5px solid #e2e8f0', borderRadius: 10,
            padding: '16px 18px', marginBottom: 16,
        }}>
            <div style={{
                fontSize: 11, fontWeight: 700, color: '#64748b', textTransform: 'uppercase',
                letterSpacing: '.06em', marginBottom: 10, display: 'flex', alignItems: 'center',
            }}>
                Terms &amp; Conditions
                <InfoTip text={TIPS.terms} label="the terms and conditions" />
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
                    ...(missing ? MISSING : {}),
                }}
            >
                <input
                    id="terms-cb"
                    type="checkbox"
                    checked={accepted}
                    disabled={disabled}
                    {...missingProps(missing)}
                    onChange={(e) => onChange(e.target.checked)}
                    style={{ width: 16, height: 16, marginTop: 1, accentColor: '#2563eb', flexShrink: 0, cursor: 'pointer' }}
                />
                <span style={{ fontSize: 13, fontWeight: 600, color: '#0f172a' }}>
                    I have read and agree to the terms and conditions above<RequiredMark />
                </span>
            </label>

            {(error || missing) && (
                <div style={{ fontSize: 12, color: '#dc2626', marginTop: 6 }}>
                    {error || 'Please accept the terms and conditions.'}
                </div>
            )}
        </div>
    );
}

export function ConfirmCodeBlock({ compact, code, value, onChange, matches, error, disabled, missing = false }) {
    const borderColor = missing ? '#ef4444' : (value.length === 0 ? '#fde68a' : (matches ? '#16a34a' : '#ef4444'));

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
                    <FieldLabel htmlFor="confirm-input" color="#92400e" tip={TIPS.confirmCode} tipLabel="the confirmation code">Paste code here<RequiredMark /></FieldLabel>
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
                        {...missingProps(missing)}
                        style={{
                            ...inputStyle,
                            ...(missing ? MISSING : {}),
                            padding: '11px 12px', borderColor,
                            fontSize: 16, fontWeight: 700, letterSpacing: '.12em',
                            fontFamily: 'ui-monospace, SFMono-Regular, Menlo, monospace',
                            textTransform: 'uppercase',
                            textAlign: compact ? 'center' : 'left',
                        }}
                    />
                    {(error || missing) && (
                        <div style={{ fontSize: 12, color: '#dc2626', marginTop: 4 }}>
                            {error || (value.trim() ? 'The code does not match. Copy it exactly as shown.' : 'Please paste the confirmation code.')}
                        </div>
                    )}
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

/**
 * The line's unit. It starts on ours; a supplier who sells it another way
 * (BAG where we asked for KG) picks their unit and says how many they are
 * supplying, and the unit price is then per theirs. What one of theirs holds
 * in ours is not asked here: we set it on the GRN when the goods arrive.
 */
export function UnitField({ item, row, units, disabled, compact, onUnit, onSupplierQty, missing = {} }) {
    const ours = item.unit;
    if (!ours) return <span style={{ color: '#64748b' }}>—</span>;

    const options = units.includes(ours) ? units : [ours, ...units];
    const changed = row.unit && row.unit !== ours;
    const box = {
        padding: compact ? '8px 10px' : '5px 8px', border: '1.5px solid #e2e8f0', borderRadius: 6,
        fontSize: compact ? 15 : 13, outline: 'none', background: '#fff', fontFamily: 'inherit',
    };

    return (
        <div style={{ minWidth: compact ? 0 : 150 }}>
            <div style={{ display: 'flex', alignItems: 'center' }}>
                <select
                    aria-label={`Unit for ${item.description}`}
                    disabled={disabled}
                    value={row.unit || ours}
                    onChange={(e) => onUnit(e.target.value)}
                    style={{ ...box, width: compact ? '100%' : 'auto', borderColor: changed ? '#f59e0b' : '#e2e8f0' }}
                >
                    {options.map((unit) => (
                        <option key={unit} value={unit}>{unit === ours ? `${unit} (as requested)` : unit}</option>
                    ))}
                </select>
                {/* On desktop the Unit column header carries this tip. */}
                {compact && <InfoTip text={TIPS.unit} label="the unit" align="right" />}
            </div>

            {changed && (
                <div style={{
                    marginTop: 6, padding: '8px 10px', background: '#fffbeb', border: '1px solid #fde68a',
                    borderRadius: 6, fontSize: 12, color: '#92400e', display: 'flex', flexDirection: 'column', gap: 6,
                }}>
                    <label style={{ display: 'flex', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
                        <span>Your qty:<RequiredMark /></span>
                        <input
                            type="number" min="0" step="any" inputMode="decimal"
                            aria-label={`Your quantity in ${row.unit}, for ${item.description}`}
                            disabled={disabled}
                            value={row.supplierQty}
                            onChange={(e) => onSupplierQty(e.target.value)}
                            {...missingProps(missing.supplierQty)}
                            style={{ ...box, width: 80, ...(missing.supplierQty ? MISSING : {}) }}
                        />
                        <span>{row.unit}</span>
                        <InfoTip text={TIPS.supplierQty} label="your quantity" />
                    </label>
                    <div style={{ fontSize: 11 }}>Your unit price is per {row.unit}.</div>
                </div>
            )}
        </div>
    );
}
