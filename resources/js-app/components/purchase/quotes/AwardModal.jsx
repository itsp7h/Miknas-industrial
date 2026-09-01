import { useEffect, useState } from 'react';
import Modal from '../../ui/Modal';
import { bd } from './quoteFormat';

/**
 * Blade's award dialog. The reason is required and at least five characters —
 * it is the audit record for why this supplier won, so it is not optional.
 */
export default function AwardModal({ target, onClose, onConfirm }) {
    const [reason, setReason] = useState('');
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        setReason('');
        setError('');
    }, [target]);

    async function confirm() {
        if (reason.trim().length < 5) {
            setError('Please give a reason of at least 5 characters.');

            return;
        }
        setSaving(true);
        setError('');
        try {
            await onConfirm(target.lineId, reason.trim());
            onClose();
        } catch (err) {
            setError(err?.errors?.award_reason?.[0] || err?.message || 'Could not award that item.');
        } finally {
            setSaving(false);
        }
    }

    return (
        <Modal open={!!target} title="Award Item" onClose={onClose}>
            {target && (
                <>
                    <div style={{ fontSize: 13, color: '#64748b' }}>{target.item}</div>
                    <div style={{ fontSize: 13, color: '#64748b' }}>{target.supplier}</div>
                    <div style={{ fontSize: 13, color: '#64748b', marginBottom: 20 }}>{bd(target.unitPrice)}</div>

                    <label htmlFor="award-reason" style={{
                        display: 'block', fontSize: 11, fontWeight: 700, color: '#64748b',
                        textTransform: 'uppercase', marginBottom: 6,
                    }}>
                        Reason for selection (required)
                    </label>
                    <textarea
                        id="award-reason" rows={3} className="form-textarea" style={{ width: '100%' }}
                        placeholder="e.g. Lowest price, reliable supplier, suitable lead time…"
                        value={reason} onChange={(e) => setReason(e.target.value)}
                    />
                    {error && <p style={{ color: '#dc2626', fontSize: 12, marginTop: 6 }}>{error}</p>}

                    <div style={{ display: 'flex', gap: 10, marginTop: 16 }}>
                        <button type="button" onClick={onClose} className="btn-secondary" style={{ flex: 1, justifyContent: 'center' }}>
                            Cancel
                        </button>
                        <button
                            type="button" onClick={confirm} disabled={saving}
                            style={{
                                flex: 2, padding: 11, background: '#f59e0b', color: '#fff', border: 'none',
                                borderRadius: 9, fontSize: 13, fontWeight: 700, cursor: saving ? 'default' : 'pointer',
                            }}
                        >
                            {saving ? 'Awarding…' : 'Confirm Award'}
                        </button>
                    </div>
                </>
            )}
        </Modal>
    );
}
