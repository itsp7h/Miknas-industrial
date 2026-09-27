import { useState } from 'react';
import SignatureCapture from '../signature/SignatureCapture';

/**
 * Profile → Signature. Shows the saved signature, or asks for one; replacing
 * it only changes LPOs issued afterwards, since each LPO keeps its own copy.
 */
export default function SignatureSettings({ signature, onSave, onRemove, compact = false }) {
    const [editing, setEditing] = useState(false);
    const [image, setImage] = useState(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    async function run(action) {
        setBusy(true);
        setError('');
        try {
            await action();
            setEditing(false);
            setImage(null);
        } catch (err) {
            setError(err?.errors?.signature_image?.[0] || err?.message || 'Could not save your signature.');
        } finally {
            setBusy(false);
        }
    }

    const full = compact ? { width: '100%', justifyContent: 'center' } : undefined;

    if (editing || !signature) {
        return (
            <div>
                <SignatureCapture key={editing ? 'replace' : 'new'} onChange={setImage} />
                {error && <p style={{ color: '#dc2626', fontSize: 13, marginTop: 8 }}>{error}</p>}
                <div style={{ display: 'flex', gap: 8, marginTop: 14, flexDirection: compact ? 'column' : 'row' }}>
                    <button
                        type="button" className="btn-primary" style={full}
                        disabled={!image || busy} onClick={() => run(() => onSave(image))}
                    >
                        {busy ? 'Saving…' : 'Save Signature'}
                    </button>
                    {signature && (
                        <button type="button" className="btn-secondary" style={full} onClick={() => { setEditing(false); setError(''); }}>
                            Cancel
                        </button>
                    )}
                </div>
            </div>
        );
    }

    return (
        <div>
            <div style={{
                border: '1px solid #e2e8f0', borderRadius: 10, padding: 12, background: '#fafafa',
                display: 'flex', justifyContent: 'center',
            }}>
                <img src={signature} alt="Your signature" style={{ maxWidth: '100%', maxHeight: 120 }} />
            </div>
            {error && <p style={{ color: '#dc2626', fontSize: 13, marginTop: 8 }}>{error}</p>}
            <div style={{ display: 'flex', gap: 8, marginTop: 14, flexDirection: compact ? 'column' : 'row' }}>
                <button type="button" className="btn-secondary" style={full} onClick={() => setEditing(true)}>
                    Replace
                </button>
                <button type="button" className="btn-danger" style={full} disabled={busy} onClick={() => run(onRemove)}>
                    Remove
                </button>
            </div>
        </div>
    );
}
