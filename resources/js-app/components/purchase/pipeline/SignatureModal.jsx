import { useEffect, useRef, useState } from 'react';
import Modal from '../../ui/Modal';

/**
 * Blade's signature pad, ported: draw with a mouse or a finger, clear, confirm.
 * When a signature already exists the same modal shows it read-only, with who
 * signed and when — the Blade page did both from one dialog too.
 */
export default function SignatureModal({ open, request, onClose, onSubmit, onReject }) {
    const canvasRef = useRef(null);
    const drawing = useRef(false);
    const dirty = useRef(false);
    const [hasInk, setHasInk] = useState(false);
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);
    // Rejecting asks for confirmation inside this dialog rather than stacking a
    // second modal on top of it.
    const [confirmingReject, setConfirmingReject] = useState(false);
    const [rejecting, setRejecting] = useState(false);

    const signature = request?.signature;
    const rejected = request?.status === 'rejected';

    useEffect(() => {
        if (!open || signature) return;
        setHasInk(false);
        dirty.current = false;
        setError('');
        setConfirmingReject(false);

        const canvas = canvasRef.current;
        // getContext returns null where 2D canvas is unavailable; the pad simply
        // cannot be drawn on then, and Confirm stays disabled.
        const context = canvas?.getContext('2d');
        if (!context) return;

        context.clearRect(0, 0, canvas.width, canvas.height);
        context.lineWidth = 2.5;
        context.lineCap = 'round';
        context.strokeStyle = '#0f172a';
    }, [open, signature]);

    function pointFrom(event) {
        const canvas = canvasRef.current;
        const rect = canvas.getBoundingClientRect();
        const source = event.touches?.[0] ?? event;

        // The canvas is drawn at its CSS size, so pointer coords need scaling.
        return {
            x: (source.clientX - rect.left) * (canvas.width / rect.width),
            y: (source.clientY - rect.top) * (canvas.height / rect.height),
        };
    }

    function start(event) {
        event.preventDefault();
        const context = canvasRef.current?.getContext('2d');
        if (!context) return;

        drawing.current = true;
        const { x, y } = pointFrom(event);
        context.beginPath();
        context.moveTo(x, y);
    }

    function move(event) {
        if (!drawing.current) return;
        event.preventDefault();
        const context = canvasRef.current?.getContext('2d');
        if (!context) return;

        const { x, y } = pointFrom(event);
        context.lineTo(x, y);
        context.stroke();
        if (!dirty.current) {
            dirty.current = true;
            setHasInk(true);
        }
    }

    const stop = () => { drawing.current = false; };

    function clear() {
        const canvas = canvasRef.current;
        canvas?.getContext('2d')?.clearRect(0, 0, canvas.width, canvas.height);
        dirty.current = false;
        setHasInk(false);
    }

    async function confirm() {
        setSaving(true);
        setError('');
        try {
            await onSubmit(canvasRef.current.toDataURL('image/png'));
            onClose();
        } catch (err) {
            setError(err?.errors?.signature_image?.[0] || err?.message || 'Could not save the signature.');
        } finally {
            setSaving(false);
        }
    }

    async function reject() {
        setRejecting(true);
        setError('');
        try {
            await onReject();
            onClose();
        } catch (rejection) {
            setError(rejection?.message || 'Could not reject that request.');
        } finally {
            setRejecting(false);
        }
    }

    return (
        <Modal
            open={open}
            title={signature ? `Signature — ${request?.request_number ?? ''}` : 'Approve or Reject'}
            onClose={onClose}
        >
            {signature ? (
                <div>
                    <div style={{ border: '1px solid #e2e8f0', borderRadius: 10, padding: 12, background: '#fafafa' }}>
                        {signature.image
                            ? <img src={signature.image} alt="Signature" style={{ maxWidth: '100%', display: 'block', margin: '0 auto' }} />
                            : <p style={{ fontSize: 13, color: '#94a3b8', textAlign: 'center', margin: 0 }}>The signature image is not available.</p>}
                    </div>
                    <p style={{ fontSize: 13, color: '#475569', marginTop: 12 }}>
                        Signed by <strong>{signature.signed_by_name ?? '—'}</strong>
                        {signature.signed_at ? ` on ${signature.signed_at}` : ''}.
                    </p>
                    <div className="mt-5 flex justify-end">
                        <button type="button" onClick={onClose} className="btn-secondary">Close</button>
                    </div>
                </div>
            ) : (
                <div>
                    {rejected && (
                        <p style={{
                            fontSize: 12.5, color: '#b91c1c', background: '#fef2f2',
                            border: '1px solid #fecaca', borderRadius: 9, padding: '9px 11px', margin: '0 0 14px',
                        }}>
                            This request was rejected. Signing it now approves it after all.
                        </p>
                    )}
                    <p style={{ fontSize: 13, color: '#475569', margin: '0 0 14px' }}>
                        Draw your signature below to approve this purchase request. Approving records your name,
                        the timestamp and your IP against it.
                    </p>

                    <div style={{ position: 'relative' }}>
                        <canvas
                            ref={canvasRef} width={510} height={180}
                            aria-label="Signature pad"
                            onMouseDown={start} onMouseMove={move} onMouseUp={stop} onMouseLeave={stop}
                            onTouchStart={start} onTouchMove={move} onTouchEnd={stop}
                            style={{
                                width: '100%', border: '2px dashed #cbd5e1', borderRadius: 10,
                                cursor: 'crosshair', touchAction: 'none', background: '#fafafa', display: 'block',
                            }}
                        />
                        {!hasInk && (
                            <div style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', pointerEvents: 'none' }}>
                                <span style={{ fontSize: 13, color: '#cbd5e1', fontStyle: 'italic' }}>Sign here</span>
                            </div>
                        )}
                    </div>

                    {error && <p style={{ color: '#dc2626', fontSize: 12, marginTop: 10 }}>{error}</p>}

                    <div style={{ display: 'flex', gap: 10, marginTop: 14 }}>
                        <button type="button" onClick={clear} className="btn-secondary" style={{ flex: 1, justifyContent: 'center' }}>
                            Clear
                        </button>
                        {/* Nothing is submitted until something is actually drawn. */}
                        <button
                            type="button" onClick={confirm} disabled={!hasInk || saving}
                            className="btn-primary" style={{ flex: 2, justifyContent: 'center' }}
                        >
                            {saving ? 'Saving…' : 'Confirm Signature →'}
                        </button>
                    </div>

                    {/* The other half of the same decision. It is the GM's call
                        either way, so it lives in the same dialog rather than
                        needing its own button on the timeline. */}
                    {onReject && !rejected && (
                        <div style={{ borderTop: '1px solid #f1f5f9', marginTop: 16, paddingTop: 14 }}>
                            {confirmingReject ? (
                                <>
                                    <p style={{ fontSize: 12.5, color: '#475569', margin: '0 0 10px' }}>
                                        Reject {request?.request_number ?? 'this request'}? It stops here rather
                                        than going on to the RFQ stage. You can still approve it later.
                                    </p>
                                    <div style={{ display: 'flex', gap: 10 }}>
                                        <button
                                            type="button" onClick={() => setConfirmingReject(false)}
                                            className="btn-secondary" style={{ flex: 1, justifyContent: 'center' }}
                                        >
                                            Keep it
                                        </button>
                                        <button
                                            type="button" onClick={reject} disabled={rejecting}
                                            className="btn-danger" style={{ flex: 1, justifyContent: 'center' }}
                                        >
                                            {rejecting ? 'Rejecting…' : 'Reject Request'}
                                        </button>
                                    </div>
                                </>
                            ) : (
                                <button
                                    type="button" onClick={() => setConfirmingReject(true)}
                                    style={{
                                        background: 'none', border: 0, padding: 0, cursor: 'pointer',
                                        fontSize: 12.5, color: '#dc2626', fontWeight: 600,
                                    }}
                                >
                                    Reject this request instead
                                </button>
                            )}
                        </div>
                    )}
                </div>
            )}
        </Modal>
    );
}
