import { useEffect } from 'react';

/**
 * A yes/no question in the app's own chrome — never `window.confirm()`
 * (CLAUDE.md #7). It sits above everything, the full-screen forms included,
 * and Escape answers "no" without also reaching the form underneath.
 */
export default function ConfirmDialog({
    open, title, body, confirmLabel = 'Confirm', cancelLabel = 'Cancel',
    tone = 'primary', busy = false, onConfirm, onCancel,
}) {
    useEffect(() => {
        if (!open) return undefined;
        // Capture on window runs before the form's own Escape listener on
        // document, so stopping it here keeps one key press to one answer.
        const onKey = (event) => {
            if (event.key !== 'Escape') return;
            event.stopPropagation();
            onCancel();
        };
        window.addEventListener('keydown', onKey, true);

        return () => window.removeEventListener('keydown', onKey, true);
    }, [open, onCancel]);

    if (!open) return null;

    return (
        <div
            role="alertdialog" aria-modal="true" aria-labelledby="confirm-dialog-title"
            onClick={(event) => { if (event.target === event.currentTarget) onCancel(); }}
            style={{
                position: 'fixed', inset: 0, zIndex: 10001, display: 'flex', alignItems: 'center',
                justifyContent: 'center', padding: '1rem', background: 'rgba(15,23,42,0.45)',
            }}
        >
            <div style={{
                width: '100%', maxWidth: '26rem', background: '#fff', borderRadius: '1rem',
                boxShadow: '0 25px 60px -10px rgba(0,0,0,0.35)', padding: '1.5rem',
            }}>
                <h2 id="confirm-dialog-title" style={{ fontSize: '1rem', fontWeight: 700, color: '#0f172a', margin: 0 }}>
                    {title}
                </h2>
                {body && <p style={{ fontSize: '0.875rem', color: '#475569', margin: '0.5rem 0 0' }}>{body}</p>}
                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.625rem', marginTop: '1.25rem', flexWrap: 'wrap' }}>
                    <button type="button" className="btn-secondary" onClick={onCancel} disabled={busy}>
                        {cancelLabel}
                    </button>
                    <button
                        type="button" autoFocus disabled={busy}
                        className={tone === 'danger' ? 'btn-danger' : 'btn-primary'}
                        onClick={onConfirm}
                    >
                        {confirmLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}
