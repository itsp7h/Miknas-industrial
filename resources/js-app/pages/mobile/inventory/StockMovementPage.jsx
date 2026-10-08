import { useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import Modal from '../../../components/ui/Modal';
import MobileAdjustmentSheet from '../../../components/inventory/movement/MobileAdjustmentSheet';
import useMovementList from '../../../components/inventory/movement/useMovementList';
import { num } from '../../../components/inventory/movement/movementStyles';
import {
    BarButton, Card, Chip, ChipRow, EmptyState, Hero, MobilePage, SearchField, SectionLabel,
} from '../../../components/mobile/ui';
import Icon from '../../../components/mobile/icons';
import { C, MONO, ZONES } from '../../../components/mobile/theme';
import { longDate, shortDate } from '../../../components/mobile/format';
import { useToast } from '../../../components/ui/Toast';
import { useAccess } from '../../../layouts/AccessContext';

// Stock movements (SteelERP-Mobile-Designs-V2): the ledger and the movement
// report in one — narrowed by date range, item and direction, grouped by day.
// All client-side over the loaded ledger (CLAUDE.md #6).

const dayKey = (value) => {
    const d = new Date(value);

    return Number.isNaN(d.getTime()) ? '' : `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

function FilterCard({ label, value, icon, onClick, children }) {
    return (
        <div style={{
            position: 'relative', flex: 1, minWidth: 0, background: C.card, borderRadius: 16, border: `1px solid ${C.line}`,
            padding: '10px 12px', display: 'flex', alignItems: 'center', gap: 10,
        }}>
            {icon && (
                <span style={{ width: 36, height: 36, borderRadius: 10, background: ZONES.inventory.soft, color: ZONES.inventory.solid, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                    <Icon name={icon} size={20} />
                </span>
            )}
            <span style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column', gap: 2 }}>
                <span style={{ fontSize: 12, color: C.muted }}>{label}</span>
                <span style={{ fontSize: 15, fontWeight: 600, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{value}</span>
            </span>
            {children ?? <Icon name="chevronDown" size={16} style={{ color: C.faint }} />}
            {onClick && (
                <button type="button" aria-label={label} onClick={onClick}
                    style={{ position: 'absolute', inset: 0, opacity: 0, border: 0, cursor: 'pointer' }} />
            )}
        </div>
    );
}

export default function StockMovementPage() {
    const m = useMovementList();
    const { showToast } = useToast();
    const canAdjust = useAccess().can('stock-movements.create');
    const [params, setParams] = useSearchParams();
    const [range, setRange] = useState({ from: '', to: '' });
    const [rangeOpen, setRangeOpen] = useState(false);
    const [itemId, setItemId] = useState('');
    const [direction, setDirection] = useState('all');

    // ?new=1 is Home's and Inventory's "Adjust stock".
    useEffect(() => {
        if (params.get('new') !== '1') return;
        if (canAdjust) m.setModalOpen(true);
        params.delete('new');
        setParams(params, { replace: true });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [params]);

    const items = useMemo(() => {
        const seen = new Map();
        m.movements.forEach((x) => { if (x.item_id && !seen.has(x.item_id)) seen.set(x.item_id, x.item_name); });

        return [...seen.entries()].sort((a, b) => String(a[1]).localeCompare(String(b[1])));
    }, [m.movements]);

    // Date and item first, so the chips count what the other two filters leave.
    const scoped = m.filtered.filter((x) => {
        const day = dayKey(x.created_at);
        if (range.from && day < range.from) return false;
        if (range.to && day > range.to) return false;

        return !itemId || String(x.item_id) === String(itemId);
    });
    const isIn = (x) => String(x.type).toLowerCase() === 'in';
    const shown = scoped.filter((x) => (direction === 'all' ? true : (direction === 'in' ? isIn(x) : !isIn(x))));

    const days = [];
    shown.forEach((x) => {
        const key = dayKey(x.created_at);
        if (!days.length || days[days.length - 1].key !== key) days.push({ key, rows: [] });
        days[days.length - 1].rows.push(x);
    });

    let rangeLabel = 'All dates';
    if (range.from && range.to) rangeLabel = `${shortDate(range.from)} – ${shortDate(range.to)}`;
    else if (range.from) rangeLabel = `From ${shortDate(range.from)}`;
    else if (range.to) rangeLabel = `Until ${shortDate(range.to)}`;

    function handleSaved(movement) {
        m.handleSaved(movement);
        showToast('Stock movement recorded.', 'success');
    }

    return (
        <MobilePage gap={14}>
            <Hero
                zone="inventory"
                back={{ to: '/app/inventory/items', label: 'Inventory' }}
                title="Movements"
                subtitle="Stock in and out · movement report"
                actions={(
                    <BarButton
                        icon="plus" label="Manual adjustment" onClick={() => m.setModalOpen(true)}
                        disabled={!canAdjust}
                        title={canAdjust ? 'Manual adjustment' : 'You do not have permission to adjust stock'}
                    />
                )}
            />

            <div style={{ display: 'flex', gap: 10 }}>
                <FilterCard label="Date range" value={rangeLabel} icon="calendar" onClick={() => setRangeOpen(true)}>
                    <span />
                </FilterCard>
                <FilterCard label="Item" value={items.find(([id]) => String(id) === String(itemId))?.[1] ?? 'All items'}>
                    <>
                        <Icon name="chevronDown" size={16} style={{ color: C.faint }} />
                        <select
                            aria-label="Filter by item" value={itemId} onChange={(e) => setItemId(e.target.value)}
                            style={{ position: 'absolute', inset: 0, opacity: 0, width: '100%', cursor: 'pointer' }}
                        >
                            <option value="">All items</option>
                            {items.map(([id, name]) => <option key={id} value={id}>{name}</option>)}
                        </select>
                    </>
                </FilterCard>
            </div>

            <SearchField value={m.query} onChange={m.setQuery} placeholder="Search movements" />

            <ChipRow>
                <Chip zone="inventory" active={direction === 'all'} onClick={() => setDirection('all')}>All · {scoped.length}</Chip>
                <Chip zone="inventory" active={direction === 'in'} onClick={() => setDirection('in')}>Stock in · {scoped.filter(isIn).length}</Chip>
                <Chip zone="inventory" active={direction === 'out'} onClick={() => setDirection('out')}>Stock out · {scoped.filter((x) => !isIn(x)).length}</Chip>
            </ChipRow>

            {shown.length === 0 ? (
                <EmptyState icon="swap" title={m.movements.length ? 'No movements match' : 'No movements recorded'} />
            ) : days.map((day) => (
                <div key={day.key} style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                    <SectionLabel zone="inventory">{longDate(day.key)}</SectionLabel>
                    <Card>
                        {day.rows.map((x, i) => {
                            const inward = isIn(x);

                            return (
                                <div key={x.id} style={{
                                    display: 'flex', alignItems: 'center', gap: 12, padding: '12px 16px',
                                    borderBottom: i === day.rows.length - 1 ? 0 : `1px solid ${C.hairline}`,
                                }}>
                                    <span style={{
                                        width: 40, height: 40, borderRadius: 20, flexShrink: 0, display: 'flex', alignItems: 'center', justifyContent: 'center',
                                        background: inward ? '#DCFCE7' : '#FEE2E2', color: inward ? '#15803D' : '#B91C1C',
                                    }}>
                                        <Icon name={inward ? 'plus' : 'minus'} size={20} strokeWidth={2.4} />
                                    </span>
                                    <span style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column', gap: 2 }}>
                                        <span style={{ fontSize: 16, fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{x.item_name ?? '—'}</span>
                                        <span style={{ fontSize: 13, color: C.muted, lineHeight: 1.4 }}>
                                            <span style={{ fontFamily: MONO }}>{x.item_code}</span>
                                            {' · '}{x.reference ?? 'Manual adjustment'}
                                            {x.warehouse_name ? ` · ${x.warehouse_name}` : ''}
                                        </span>
                                        {x.notes && <span style={{ fontSize: 13, color: C.faint }}>{x.notes}</span>}
                                    </span>
                                    <span style={{ fontSize: 17, fontWeight: 700, color: inward ? '#15803D' : '#B91C1C', flexShrink: 0 }}>
                                        {inward ? '+' : '−'}{num(x.quantity)}
                                    </span>
                                </div>
                            );
                        })}
                    </Card>
                </div>
            ))}

            <Link to="/app/inventory/reports/movement" style={{ alignSelf: 'center', color: C.accent, fontSize: 15, fontWeight: 500, textDecoration: 'none', padding: 8 }}>
                Open the full movement report
            </Link>

            <Modal open={rangeOpen} title="Date range" onClose={() => setRangeOpen(false)}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <div>
                        <label className="form-label" htmlFor="mv-from">From</label>
                        <input id="mv-from" type="date" className="form-input" value={range.from} onChange={(e) => setRange((r) => ({ ...r, from: e.target.value }))} />
                    </div>
                    <div>
                        <label className="form-label" htmlFor="mv-to">To</label>
                        <input id="mv-to" type="date" className="form-input" value={range.to} onChange={(e) => setRange((r) => ({ ...r, to: e.target.value }))} />
                    </div>
                    <div style={{ display: 'flex', gap: 10 }}>
                        <button type="button" className="btn btn-secondary" style={{ flex: 1, justifyContent: 'center' }} onClick={() => { setRange({ from: '', to: '' }); setRangeOpen(false); }}>
                            All dates
                        </button>
                        <button type="button" className="btn btn-primary" style={{ flex: 1, justifyContent: 'center' }} onClick={() => setRangeOpen(false)}>
                            Done
                        </button>
                    </div>
                </div>
            </Modal>

            <MobileAdjustmentSheet open={m.modalOpen} onClose={() => m.setModalOpen(false)} onSaved={handleSaved} />
        </MobilePage>
    );
}
