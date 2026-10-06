import { useEffect, useState } from 'react';
import Modal from '../../ui/Modal';
import { bd } from './quoteFormat';

/**
 * Confirms "award all": how many items, to whom, and — when some are held by
 * other suppliers — that they move. One reason goes on every line, held to the
 * same rule as a single award: it is the audit record.
 */
export default function AwardAllModal({ target, onClose, onConfirm }) {
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
            await onConfirm(target.quote_id, reason.trim());
            onClose();
        } catch (err) {
            setError(err?.errors?.award_reason?.[0] || err?.message || 'Could not award those items.');
        } finally {
            setSaving(false);
        }
    }

    return (
        <Modal open={!!target} title={target ? `Award all to ${target.supplier}` : ''} onClose={onClose}>
            {target && (
                <>
                    <div style={{ fontSize: 13, color: '#334155', marginBottom: 16 }}>
                        Awards <strong>{target.remaining} {target.remaining === 1 ? 'item' : 'items'}</strong> to {target.supplier}
                        {' '}— everything they quoted and could supply ({bd(target.total)} before VAT).
                    </div>

                    {target.held_elsewhere > 0 && (
                        <div style={{
                            fontSize: 12, color: '#b45309', background: '#fffbeb', border: '1px solid #fde68a',
                            borderRadius: 8, padding: '8px 10px', marginBottom: 16,
                        }}>
                            {target.held_elsewhere} of them {target.held_elsewhere === 1 ? 'is' : 'are'} awarded to another
                            supplier. Confirming moves {target.held_elsewhere === 1 ? 'it' : 'them'} to {target.supplier}.
                        </div>
                    )}

                    <label htmlFor="award-all-reason" style={{
                        display: 'block', fontSize: 11, fontWeight: 700, color: '#64748b',
                        textTransform: 'uppercase', marginBottom: 6,
                    }}>
                        Reason for selection (required)
                    </label>
                    <textarea
                        id="award-all-reason" rows={3} className="form-textarea" style={{ width: '100%' }}
                        placeholder="e.g. Only supplier quoted, best overall price…"
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
                            {saving ? 'Awarding…' : `Award ${target.remaining} ${target.remaining === 1 ? 'item' : 'items'}`}
                        </button>
                    </div>
                </>
            )}
        </Modal>
    );
}
