import useFinanceSettings from './useFinanceSettings';

const CARD = { background: '#fff', border: '1px solid #e2e8f0', borderRadius: 14, overflow: 'hidden', marginBottom: 16 };
const HEAD = { padding: '1.25rem 1.5rem', borderBottom: '1px solid #e2e8f0', background: '#f8fafc' };
const HEAD_TEXT = { fontSize: 11, fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: '.05em' };
const BODY = { padding: '1.5rem' };

function SaveButton({ onClick, disabled, children }) {
    return (
        <button
            type="button" onClick={onClick} disabled={disabled}
            style={{
                marginTop: 20, padding: '10px 24px', background: '#2563eb', color: '#fff',
                border: 'none', borderRadius: 8, fontSize: 13, fontWeight: 600,
                cursor: disabled ? 'default' : 'pointer', opacity: disabled ? 0.6 : 1,
            }}
        >
            {children}
        </button>
    );
}

/**
 * The two money settings, each saving on its own.
 *
 * One tree rather than a desktop/mobile pair: the only difference is the
 * card's width, which the caller sets.
 */
export default function FinanceCards({ maxWidth = 480 }) {
    const f = useFinanceSettings();

    return (
        <div style={{ maxWidth }}>
            <div style={CARD}>
                <div style={HEAD}><div style={HEAD_TEXT}>VAT Configuration</div></div>
                <div style={BODY}>
                    <label htmlFor="vat-rate" className="form-label">VAT Rate (%)</label>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                        <input
                            id="vat-rate" type="number" min="0" max="100" step="0.01" placeholder="e.g. 10"
                            value={f.loading ? '' : f.rate}
                            onChange={(e) => f.setRate(e.target.value)}
                            style={{
                                width: 160, padding: '9px 12px', border: '1.5px solid #e2e8f0',
                                borderRadius: 8, fontSize: 14, fontWeight: 600, outline: 'none',
                            }}
                        />
                        <span style={{ fontSize: 14, color: '#64748b', fontWeight: 500 }}>%</span>
                    </div>
                    <p style={{ fontSize: 12, color: '#94a3b8', marginTop: 8 }}>
                        Enter 0 to disable VAT. Suppliers will see a VAT checkbox on each item when this is greater than 0.
                    </p>
                    {f.errors.vat_rate && <p style={{ fontSize: 12, color: '#dc2626', marginTop: 8 }}>{f.errors.vat_rate}</p>}
                    <SaveButton onClick={() => f.save('vat_rate')} disabled={f.loading || f.saving !== null}>
                        {f.saving === 'vat_rate' ? 'Saving…' : 'Save VAT Rate'}
                    </SaveButton>
                </div>
            </div>

            <div style={CARD}>
                <div style={HEAD}><div style={HEAD_TEXT}>Currency</div></div>
                <div style={BODY}>
                    <label htmlFor="currency-code" className="form-label">Default Currency</label>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                        <select
                            id="currency-code"
                            value={f.loading ? '' : f.currency}
                            onChange={(e) => f.setCurrency(e.target.value)}
                            style={{
                                width: 200, padding: '9px 12px', border: '1.5px solid #e2e8f0',
                                borderRadius: 8, fontSize: 14, fontWeight: 600, outline: 'none', background: '#fff',
                            }}
                        >
                            {f.currencies.map((option) => (
                                <option key={option.code} value={option.code}>{option.label}</option>
                            ))}
                        </select>
                        {f.symbol && (
                            <span style={{ fontSize: 14, color: '#64748b', fontWeight: 500 }}>{f.symbol}</span>
                        )}
                    </div>
                    <p style={{ fontSize: 12, color: '#94a3b8', marginTop: 8 }}>
                        The currency amounts are shown in across the app. Bahraini Dinar is written BD.
                    </p>
                    {f.errors.currency_code && <p style={{ fontSize: 12, color: '#dc2626', marginTop: 8 }}>{f.errors.currency_code}</p>}
                    <SaveButton onClick={() => f.save('currency_code')} disabled={f.loading || f.saving !== null}>
                        {f.saving === 'currency_code' ? 'Saving…' : 'Save Currency'}
                    </SaveButton>
                </div>
            </div>
        </div>
    );
}
