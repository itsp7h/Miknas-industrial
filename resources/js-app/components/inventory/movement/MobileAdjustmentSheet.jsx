import { useEffect, useMemo, useState } from 'react';
import Modal from '../../ui/Modal';
import { apiGet, apiPost } from '../../../api/client';
import { num } from './movementStyles';
import { C, MONO } from '../../mobile/theme';

// The phone's manual adjustment (SteelERP-Mobile-Designs-V2): pick the item
// and warehouse, in or out, a big stepper, and what the stock becomes —
// worked out from the levels the form options carry — before it is recorded.
// Posts exactly what StockMovementForm posts.

const EMPTY = { item_id: '', warehouse_id: '', type: 'in', quantity: '1', notes: '' };

export default function MobileAdjustmentSheet({ open, onClose, onSaved }) {
    const [options, setOptions] = useState(null);
    const [loadError, setLoadError] = useState(null);
    const [values, setValues] = useState(EMPTY);
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!open) return;
        setValues(EMPTY);
        setErrors({});
        apiGet('/inventory/movements/form-options')
            .then((data) => {
                setOptions(data);
                // One warehouse is not a choice.
                if (data.warehouses?.length === 1) setValues((v) => ({ ...v, warehouse_id: String(data.warehouses[0].id) }));
            })
            .catch(() => setLoadError('Could not load items and warehouses.'));
    }, [open]);

    const set = (name, value) => setValues((prev) => ({ ...prev, [name]: value }));
    const item = options?.items.find((i) => String(i.id) === String(values.item_id));
    const onHand = useMemo(() => {
        if (!options || !values.item_id || !values.warehouse_id) return null;
        const level = (options.levels ?? []).find((l) => String(l.item_id) === String(values.item_id)
            && String(l.warehouse_id) === String(values.warehouse_id));

        return Number(level?.quantity ?? 0);
    }, [options, values.item_id, values.warehouse_id]);

    const qty = Number(values.quantity) || 0;
    const out = values.type === 'out';
    const becomes = onHand === null ? null : onHand + (out ? -qty : qty);
    const step = (by) => set('quantity', String(Math.max(0, Math.round((qty + by) * 100) / 100)));

    async function submit(e) {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const response = await apiPost('/inventory/movements', values);
            onSaved(response.data);
        } catch (err) {
            setErrors(Object.fromEntries(Object.entries(err.errors ?? {}).map(([k, m]) => [k, m[0]])));
            if (!err.errors) setErrors({ form: err.message || 'Could not record that movement.' });
        } finally {
            setSaving(false);
        }
    }

    const round = {
        width: 60, height: 60, borderRadius: 30, border: `1px solid ${C.line}`, background: '#FFFFFF',
        fontSize: 28, color: C.text, cursor: 'pointer', flexShrink: 0,
    };
    const toggle = (on, danger) => ({
        flex: 1, height: 52, borderRadius: 14, font: 'inherit', fontSize: 16, fontWeight: 600, cursor: 'pointer',
        border: `1.5px solid ${on ? (danger ? '#B91C1C' : '#15803D') : C.line}`,
        background: on ? (danger ? '#FEE2E2' : '#DCFCE7') : '#FFFFFF',
        color: on ? (danger ? '#991B1B' : '#166534') : C.text2,
    });
    const err = (name) => errors[name] && <p style={{ margin: '6px 0 0', fontSize: 13, color: '#DC2626' }}>{errors[name]}</p>;

    return (
        <Modal open={open} title="Manual adjustment" onClose={onClose}>
            {loadError ? <p style={{ color: '#DC2626' }}>{loadError}</p> : (
                <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <div>
                        <label className="form-label" htmlFor="adj-item">Item</label>
                        <select id="adj-item" className="form-select" value={values.item_id} onChange={(e) => set('item_id', e.target.value)}>
                            <option value="">Select an item…</option>
                            {(options?.items ?? []).map((i) => <option key={i.id} value={i.id}>{i.item_name} ({i.item_code})</option>)}
                        </select>
                        {item && (
                            <p style={{ margin: '6px 0 0', fontFamily: MONO, fontSize: 13, color: C.muted }}>
                                {item.item_code}{onHand !== null ? ` · on hand ${num(onHand)} ${item.unit_of_measure ?? ''}` : ''}
                            </p>
                        )}
                        {err('item_id')}
                    </div>
                    <div>
                        <label className="form-label" htmlFor="adj-warehouse">Warehouse</label>
                        <select id="adj-warehouse" className="form-select" value={values.warehouse_id} onChange={(e) => set('warehouse_id', e.target.value)}>
                            <option value="">Select a warehouse…</option>
                            {(options?.warehouses ?? []).map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
                        </select>
                        {err('warehouse_id')}
                    </div>

                    <div role="radiogroup" aria-label="Movement type" style={{ display: 'flex', gap: 10 }}>
                        <button type="button" role="radio" aria-checked={!out} onClick={() => set('type', 'in')} style={toggle(!out, false)}>Stock in</button>
                        <button type="button" role="radio" aria-checked={out} onClick={() => set('type', 'out')} style={toggle(out, true)}>Stock out</button>
                    </div>

                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                        <button type="button" aria-label="Decrease quantity" onClick={() => step(-1)} style={round}>−</button>
                        <div style={{ flex: 1, textAlign: 'center' }}>
                            <input
                                type="number" inputMode="decimal" min="0" step="0.01" aria-label="Quantity"
                                value={values.quantity} onChange={(e) => set('quantity', e.target.value)}
                                style={{ width: '100%', border: 0, textAlign: 'center', fontSize: 44, fontWeight: 700, color: C.text, outline: 'none', background: 'transparent' }}
                            />
                            <div style={{ fontSize: 14, color: C.muted }}>
                                {item?.unit_of_measure ?? ''}{becomes !== null ? ` · stock becomes ${num(becomes)}` : ''}
                            </div>
                        </div>
                        <button type="button" aria-label="Increase quantity" onClick={() => step(1)} style={round}>+</button>
                    </div>
                    {err('quantity')}
                    {becomes !== null && becomes < 0 && (
                        <p style={{ margin: 0, fontSize: 13, color: '#B91C1C' }}>That is more than this warehouse holds.</p>
                    )}

                    <div>
                        <label className="form-label" htmlFor="adj-notes">Notes</label>
                        <input
                            id="adj-notes" className="form-input" placeholder="Reason, e.g. damaged pair written off"
                            value={values.notes} onChange={(e) => set('notes', e.target.value)}
                        />
                        {err('notes')}
                    </div>
                    {err('form')}

                    <button
                        type="submit" disabled={saving}
                        style={{
                            height: 56, borderRadius: 16, border: 0, color: '#FFFFFF', font: 'inherit', fontSize: 17,
                            fontWeight: 600, background: out ? '#B91C1C' : '#15803D', cursor: 'pointer', opacity: saving ? 0.6 : 1,
                        }}
                    >
                        {saving ? 'Recording…' : 'Record movement'}
                    </button>
                </form>
            )}
        </Modal>
    );
}
