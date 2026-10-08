import { useEffect, useRef } from 'react';
import useFinanceSettings from '../../../components/settings/finance/useFinanceSettings';
import { BarButton, Card, Hero, Loading, MobilePage, SectionLabel } from '../../../components/mobile/ui';
import { C, ZONES } from '../../../components/mobile/theme';
import { CURRENCIES } from '../../../currency';
import { useAccess } from '../../../layouts/AccessContext';

// System → Finance (SteelERP-Mobile-Designs-V2): the VAT rate on a stepper,
// the currency, and a worked example of both. Save in the bar saves whichever
// of the two changed — each is still its own field on the API.

export default function FinancePage() {
    const f = useFinanceSettings();
    const canEdit = useAccess().can('finance.edit');
    const loaded = useRef(null);

    useEffect(() => {
        if (!f.loading && loaded.current === null) loaded.current = { rate: f.rate, currency: f.currency };
    }, [f.loading, f.rate, f.currency]);

    const rate = Number(f.rate) || 0;
    const step = (by) => f.setRate(String(Math.min(100, Math.max(0, Math.round((rate + by) * 100) / 100))));
    const meta = CURRENCIES[f.currency] ?? { symbol: f.symbol, decimals: 3 };
    const fmt = (n) => `${meta.symbol} ${n.toLocaleString(undefined, { minimumFractionDigits: meta.decimals, maximumFractionDigits: meta.decimals })}`;
    const option = f.currencies.find((c) => c.code === f.currency);
    const dirty = loaded.current && (loaded.current.rate !== f.rate || loaded.current.currency !== f.currency);

    async function save() {
        if (loaded.current.rate !== f.rate) await f.save('vat_rate');
        if (loaded.current.currency !== f.currency) await f.save('currency_code');
        loaded.current = { rate: f.rate, currency: f.currency };
    }

    const round = {
        width: 60, height: 60, borderRadius: 30, border: `1px solid ${C.line}`, background: '#FFFFFF',
        fontSize: 28, color: C.text, cursor: canEdit ? 'pointer' : 'not-allowed', flexShrink: 0,
    };

    return (
        <MobilePage gap={16}>
            <Hero
                zone="system"
                back={{ to: '/app/more', label: 'More' }}
                title="Finance"
                subtitle="The VAT rate applied to vatable items, and the currency amounts are shown in."
                actions={(
                    <BarButton
                        label="Save finance settings" onClick={save}
                        disabled={!canEdit || !dirty || f.saving !== null}
                        title={canEdit ? 'Save' : 'You do not have permission to change the finance settings'}
                    >
                        {f.saving ? 'Saving…' : 'Save'}
                    </BarButton>
                )}
            />

            {f.loading ? <Loading /> : (
                <>
                    <SectionLabel zone="system">VAT</SectionLabel>
                    <Card padded style={{ alignItems: 'center', gap: 16, padding: '24px 16px' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 20 }}>
                            <button type="button" aria-label="Lower the VAT rate" disabled={!canEdit} onClick={() => step(-1)} style={round}>−</button>
                            <label style={{ display: 'flex', alignItems: 'baseline' }}>
                                <input
                                    type="number" inputMode="decimal" min="0" max="100" step="0.01" aria-label="VAT rate"
                                    disabled={!canEdit} value={f.rate} onChange={(e) => f.setRate(e.target.value)}
                                    style={{
                                        width: `${Math.max(1, String(f.rate).length)}ch`, minWidth: '1ch', border: 0, background: 'transparent',
                                        fontSize: 56, fontWeight: 700, color: ZONES.system.solid, textAlign: 'right', outline: 'none', padding: 0,
                                    }}
                                />
                                <span style={{ fontSize: 56, fontWeight: 700, color: ZONES.system.solid }}>%</span>
                            </label>
                            <button type="button" aria-label="Raise the VAT rate" disabled={!canEdit} onClick={() => step(1)} style={round}>+</button>
                        </div>
                        <p style={{ margin: 0, fontSize: 14, color: C.muted, textAlign: 'center', lineHeight: 1.45 }}>
                            Suppliers see a VAT checkbox on each item. Set 0 to turn VAT off.
                        </p>
                        {f.errors.vat_rate && <p style={{ margin: 0, fontSize: 13, color: '#DC2626' }}>{f.errors.vat_rate}</p>}
                    </Card>

                    <SectionLabel zone="system">Currency</SectionLabel>
                    <div style={{ position: 'relative', background: C.card, borderRadius: 18, minHeight: 56, padding: '0 16px', display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12 }}>
                        <span style={{ fontSize: 16 }}>Default currency</span>
                        <span style={{ fontSize: 16, color: C.muted }}>{f.currency}{option?.symbol ? ` (${option.symbol})` : ''} ›</span>
                        <select
                            aria-label="Default currency" disabled={!canEdit} value={f.currency}
                            onChange={(e) => f.setCurrency(e.target.value)}
                            style={{ position: 'absolute', inset: 0, opacity: 0, width: '100%', cursor: canEdit ? 'pointer' : 'not-allowed' }}
                        >
                            {f.currencies.map((c) => <option key={c.code} value={c.code}>{c.label}</option>)}
                        </select>
                    </div>
                    <span style={{ fontSize: 14, color: C.muted, padding: '0 4px', marginTop: -8 }}>
                        Amounts are written {meta.symbol}, with {meta.decimals === 3 ? 'three' : 'two'} decimals.
                    </span>
                    {f.errors.currency_code && <p style={{ margin: 0, fontSize: 13, color: '#DC2626' }}>{f.errors.currency_code}</p>}

                    <SectionLabel zone="system">Preview</SectionLabel>
                    <Card padded style={{ gap: 8 }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 16, color: C.muted }}><span>Subtotal</span><span>{fmt(100)}</span></div>
                        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 16, color: C.muted }}><span>VAT {rate}%</span><span>{fmt(rate)}</span></div>
                        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 17, fontWeight: 700, borderTop: `1px solid ${C.hairline}`, paddingTop: 10, marginTop: 4 }}>
                            <span>Total</span><span>{fmt(100 + rate)}</span>
                        </div>
                    </Card>
                </>
            )}
        </MobilePage>
    );
}
