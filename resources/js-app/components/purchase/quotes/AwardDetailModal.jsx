import { useState } from 'react';
import Modal from '../../ui/Modal';
import { bd } from './quoteFormat';

function Field({ label, value, colour = '#0f172a', weight = 600 }) {
    return (
        <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
            <dt style={{ color: '#64748b' }}>{label}</dt>
            <dd style={{ color: colour, fontWeight: weight, textAlign: 'right', margin: 0 }}>{value}</dd>
        </div>
    );
}

/** Blade's award-detail dialog: why this supplier won, and the way to undo it. */
export default function AwardDetailModal({ detail, canAward, onClose, onRemove }) {
    const [removing, setRemoving] = useState(false);
    const [error, setError] = useState('');

    async function remove() {
        setRemoving(true);
        setError('');
        try {
            await onRemove(detail.lineId);
            onClose();
        } catch (err) {
            setError(err?.message || 'Could not remove that award.');
        } finally {
            setRemoving(false);
        }
    }

    return (
        <Modal open={!!detail} title="Award Details" onClose={onClose}>
            {detail && (
                <>
                    <dl style={{ display: 'flex', flexDirection: 'column', gap: 8, fontSize: 13, margin: '0 0 18px' }}>
                        <Field label="Item" value={detail.item} />
                        <Field label="Awarded to" value={detail.supplier} colour="#15803d" weight={700} />
                        <Field label="Unit Price" value={bd(detail.unitPrice)} />
                        <Field label="Total" value={bd(detail.totalPrice)} />
                        {detail.awardedBy && <Field label="Awarded by" value={detail.awardedBy} />}
                        {detail.awardedAt && <Field label="When" value={detail.awardedAt} />}
                    </dl>

                    {detail.reason && (
                        <div style={{ background: '#f8fafc', border: '1px solid #f1f5f9', borderRadius: 8, padding: '9px 12px', marginBottom: 18 }}>
                            <div style={{ fontSize: 10, fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '.05em', marginBottom: 3 }}>
                                Reason
                            </div>
                            <div style={{ fontSize: 13, color: '#374151' }}>{detail.reason}</div>
                        </div>
                    )}

                    {error && <p style={{ color: '#dc2626', fontSize: 12, marginBottom: 10 }}>{error}</p>}

                    <div style={{ display: 'flex', gap: 10 }}>
                        <button type="button" onClick={onClose} className="btn-secondary" style={{ flex: 1, justifyContent: 'center' }}>
                            Close
                        </button>
                        {/* Removing the award frees the item for a different
                            supplier, and drops the request back out of the LPO
                            stage if it had reached it. */}
                        {canAward && (
                            <button
                                type="button" onClick={remove} disabled={removing}
                                style={{
                                    flex: 2, padding: 11, background: '#fff', color: '#dc2626',
                                    border: '1.5px solid #fecaca', borderRadius: 9, fontSize: 13,
                                    fontWeight: 700, cursor: removing ? 'default' : 'pointer',
                                }}
                            >
                                {removing ? 'Removing…' : 'Remove Award'}
                            </button>
                        )}
                    </div>
                </>
            )}
        </Modal>
    );
}
