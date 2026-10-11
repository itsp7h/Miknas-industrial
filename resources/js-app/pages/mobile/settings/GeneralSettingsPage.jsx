import useCompanyWarehouse from '../../../components/settings/companyWarehouse/useCompanyWarehouse';
import useDocumentNumbering, { previewFor } from '../../../components/settings/documentNumbering/useDocumentNumbering';
import { BarButton, Card, Hero, Loading, MobilePage, SectionLabel } from '../../../components/mobile/ui';
import { C, MONO } from '../../../components/mobile/theme';
import { useAccess } from '../../../layouts/AccessContext';

// System → Settings (SteelERP-Mobile-Designs-V2): each company's document
// code with the next MPR and LPO numbers it gives, and the warehouse each
// company receives into. One Save in the bar saves whichever changed.

function Preview({ label, value }) {
    return (
        <div style={{ flex: 1, minWidth: 0, background: '#F8FAFC', borderRadius: 12, padding: '10px 12px' }}>
            <div style={{ fontSize: 12, fontWeight: 600, color: C.muted, letterSpacing: '0.04em', textTransform: 'uppercase' }}>{label}</div>
            <div style={{ fontFamily: MONO, fontSize: 14, marginTop: 4, overflowWrap: 'anywhere' }}>{value}</div>
        </div>
    );
}

export default function GeneralSettingsPage() {
    const n = useDocumentNumbering();
    const w = useCompanyWarehouse();
    const canEdit = useAccess().can('settings.edit');
    const dirty = n.dirty || w.dirty;
    const saving = n.saving || w.saving;

    async function save() {
        if (n.dirty) await n.save();
        if (w.dirty) await w.save();
    }

    return (
        <MobilePage gap={16}>
            <Hero
                zone="system"
                back={{ to: '/app/more', label: 'More' }}
                title="Settings"
                subtitle="Document numbering and warehouses"
                actions={(
                    <BarButton
                        label="Save settings" onClick={save} disabled={!canEdit || !dirty || saving}
                        title={canEdit ? 'Save' : 'You do not have permission to change the settings'}
                    >
                        {saving ? 'Saving…' : 'Save'}
                    </BarButton>
                )}
            />

            <SectionLabel zone="system">Document numbering</SectionLabel>
            <span style={{ fontSize: 14, color: C.muted, padding: '0 4px', lineHeight: 1.45 }}>
                Each company numbers its own requests and orders from one code. Both start again at 0001 each year.
            </span>
            {n.loading ? <Loading /> : n.companies.map((company) => (
                <Card key={company.id} padded style={{ gap: 12 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                        <span style={{ flex: 1, minWidth: 0, fontSize: 17, fontWeight: 600 }}>{company.name}</span>
                        <label style={{ display: 'flex', alignItems: 'center', gap: 10, color: C.muted, fontSize: 15 }}>
                            Code
                            <input
                                aria-label={`${company.name} document code`}
                                disabled={!canEdit}
                                value={n.codes[company.id] ?? ''}
                                onChange={(e) => n.setCode(company.id, e.target.value)}
                                placeholder="—"
                                style={{
                                    width: 80, height: 48, borderRadius: 12, border: `1px solid ${C.line}`, textAlign: 'center',
                                    fontFamily: MONO, fontSize: 17, textTransform: 'uppercase', color: C.text, outline: 'none',
                                }}
                            />
                        </label>
                    </div>
                    <div style={{ display: 'flex', gap: 10 }}>
                        <Preview label="Next MPR" value={previewFor(n.codes[company.id], company.next_mpr_number, company.mpr_code || 'MPR')} />
                        <Preview label="Next LPO" value={previewFor(n.codes[company.id], company.next_number, 'LPO')} />
                    </div>
                </Card>
            ))}
            {n.error && <p role="alert" style={{ margin: 0, fontSize: 13, color: '#DC2626' }}>{n.error}</p>}

            <SectionLabel zone="system">Receiving warehouse</SectionLabel>
            {w.loading ? <Loading /> : (
                <Card>
                    {w.companies.map((company, i) => {
                        const linked = w.warehouses.find((x) => String(x.id) === String(w.links[company.id] ?? ''));

                        return (
                            <div key={company.id} style={{
                                position: 'relative', display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12,
                                minHeight: 56, padding: '0 16px', borderBottom: i === w.companies.length - 1 ? 0 : `1px solid ${C.hairline}`,
                            }}>
                                <span style={{ fontSize: 16 }}>{company.name}</span>
                                <span style={{ fontSize: 15, color: C.muted, textAlign: 'right' }}>{linked?.name ?? 'No warehouse'} ›</span>
                                <select
                                    aria-label={`${company.name} receiving warehouse`}
                                    disabled={!canEdit}
                                    value={w.links[company.id] ?? ''}
                                    onChange={(e) => w.setLink(company.id, e.target.value)}
                                    style={{ position: 'absolute', inset: 0, opacity: 0, width: '100%', cursor: canEdit ? 'pointer' : 'not-allowed' }}
                                >
                                    <option value="">— No warehouse —</option>
                                    {w.warehouses.map((x) => <option key={x.id} value={x.id}>{x.name}{!x.is_active ? ' (inactive)' : ''}</option>)}
                                </select>
                            </div>
                        );
                    })}
                </Card>
            )}
            <span style={{ fontSize: 14, color: C.muted, padding: '0 4px', lineHeight: 1.45 }}>
                Goods bought against a company&rsquo;s request are received into its warehouse. Leave it unset to choose on each receipt.
            </span>
            {w.error && <p role="alert" style={{ margin: 0, fontSize: 13, color: '#DC2626' }}>{w.error}</p>}
        </MobilePage>
    );
}
