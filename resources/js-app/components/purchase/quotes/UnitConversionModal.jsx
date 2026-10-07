import { useEffect, useState } from 'react';
import Modal from '../../ui/Modal';
import { bd } from './quoteFormat';

/**
 * Corrects how a line the supplier quoted in their own unit maps to ours —
 * what one of theirs holds, and how many they are supplying — before it is
 * awarded. Their price per unit is what they quoted, so it is not editable.
 */
export default function UnitConversionModal({ target, onClose, onConfirm }) {
    const [factor, setFactor] = useState('');
    const [supplierQty, setSupplierQty] = useState('');
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        setFactor(target?.factor ? String(target.factor) : '');
        setSupplierQty(target ? String(target.supplierQty) : '');
        setError('');
    }, [target]);

    const perUnit = parseFloat(factor);
    const count = parseFloat(supplierQty);
    const valid = perUnit > 0 && count > 0;

    async function confirm() {
        if (!valid) {
            setError('Both numbers must be greater than zero.');

            return;
        }
        setSaving(true);
        setError('');
        try {
            await onConfirm(target.lineId, { unit_factor: perUnit, supplier_quantity: count });
            onClose();
        } catch (err) {
            setError(err?.message || 'Could not change the conversion.');
        } finally {
            setSaving(false);
        }
    }

    const field = { width: 100, padding: '7px 10px', border: '1.5px solid #e2e8f0', borderRadius: 8, fontSize: 14 };

    return (
        <Modal open={!!target} title="Supplier's Unit" onClose={onClose}>
            {target && (
                <>
                    <div style={{ fontSize: 13, color: '#64748b' }}>{target.item} · {target.supplier}</div>
                    <div style={{ fontSize: 13, color: '#64748b', marginBottom: 18 }}>
                        Quoted at {bd(target.supplierPrice)} per {target.supplierUnit}; we asked for {target.requested} {target.unit}.
                    </div>

                    <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 14, marginBottom: 12 }}>
                        1 {target.supplierUnit} =
                        <input
                            type="number" min="0" step="any" aria-label={`How many ${target.unit} one ${target.supplierUnit} holds`}
                            value={factor} onChange={(e) => setFactor(e.target.value)} style={field}
                        />
                        {target.unit}
                    </label>
                    <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 14 }}>
                        Quantity:
                        <input
                            type="number" min="0" step="any" aria-label={`Quantity in ${target.supplierUnit}`}
                            value={supplierQty} onChange={(e) => setSupplierQty(e.target.value)} style={field}
                        />
                        {target.supplierUnit}
                    </label>

                    {valid && (
                        <div style={{
                            marginTop: 14, padding: '10px 12px', background: '#fffbeb', border: '1px solid #fde68a',
                            borderRadius: 8, fontSize: 13, color: '#92400e',
                        }}>
                            = <strong>{Math.round(count * perUnit * 1000) / 1000} {target.unit}</strong> into stock
                            · {bd(target.supplierPrice / perUnit)} per {target.unit}
                            · total {bd(count * target.supplierPrice)}
                        </div>
                    )}
                    {error && <p style={{ color: '#dc2626', fontSize: 12, marginTop: 6 }}>{error}</p>}

                    <div style={{ display: 'flex', gap: 10, marginTop: 16 }}>
                        <button type="button" onClick={onClose} className="btn-secondary" style={{ flex: 1, justifyContent: 'center' }}>
                            Cancel
                        </button>
                        <button type="button" onClick={confirm} disabled={saving} className="btn-primary" style={{ flex: 2, justifyContent: 'center' }}>
                            {saving ? 'Saving…' : 'Save Conversion'}
                        </button>
                    </div>
                </>
            )}
        </Modal>
    );
}
