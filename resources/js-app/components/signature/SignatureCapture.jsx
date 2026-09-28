import { useEffect, useRef, useState } from 'react';
import { readImageFile } from '../image/readImage';

/** The largest the stored image gets; the LPO prints it at most 200×60. */
const BOX = { maxW: 600, maxH: 200 };

const TAB = (active) => ({
    fontSize: 13, fontWeight: 600, padding: '6px 14px', borderRadius: 8, border: 0, cursor: 'pointer',
    background: active ? '#2563eb' : '#f1f5f9', color: active ? '#fff' : '#475569',
});

/**
 * Draw a signature, or upload an image of one. Reports a PNG/JPEG data URL
 * through `onChange`, or null while there is nothing to save.
 *
 * The pad is the one the GM's Approve & Sign dialog uses: a canvas drawn at a
 * fixed resolution and shown at the width available.
 */
export default function SignatureCapture({ onChange }) {
    const [mode, setMode] = useState('draw');
    const canvasRef = useRef(null);
    const drawing = useRef(false);
    const [hasInk, setHasInk] = useState(false);
    const [upload, setUpload] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        if (mode !== 'draw') return;
        const context = canvasRef.current?.getContext('2d');
        if (!context) return;
        context.lineWidth = 2.5;
        context.lineCap = 'round';
        context.strokeStyle = '#0f172a';
    }, [mode]);

    function switchTo(next) {
        setMode(next);
        setHasInk(false);
        setUpload(null);
        setError('');
        onChange(null);
    }

    function pointFrom(event) {
        const canvas = canvasRef.current;
        const rect = canvas.getBoundingClientRect();
        const source = event.touches?.[0] ?? event;

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
        setHasInk(true);
    }

    function stop() {
        if (!drawing.current) return;
        drawing.current = false;
        if (hasInk) onChange(canvasRef.current.toDataURL('image/png'));
    }

    function clear() {
        const canvas = canvasRef.current;
        canvas?.getContext('2d')?.clearRect(0, 0, canvas.width, canvas.height);
        setHasInk(false);
        onChange(null);
    }

    function pick(event) {
        const file = event.target.files?.[0];
        event.target.value = '';
        setError('');
        if (!file) return;

        readImageFile(file, BOX).then((dataUrl) => {
            setUpload(dataUrl);
            onChange(dataUrl);
        }, (err) => setError(err.message));
    }

    return (
        <div>
            <div role="tablist" aria-label="How to add your signature" style={{ display: 'flex', gap: 8, marginBottom: 12 }}>
                <button type="button" role="tab" aria-selected={mode === 'draw'} onClick={() => switchTo('draw')} style={TAB(mode === 'draw')}>
                    ✍️ Draw
                </button>
                <button type="button" role="tab" aria-selected={mode === 'upload'} onClick={() => switchTo('upload')} style={TAB(mode === 'upload')}>
                    ⬆️ Upload
                </button>
            </div>

            {mode === 'draw' ? (
                <div>
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
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: 6 }}>
                        <span style={{ fontSize: 12, color: '#94a3b8' }}>Sign with your mouse or finger.</span>
                        <button type="button" onClick={clear} disabled={!hasInk} className="btn-secondary btn-sm">Clear</button>
                    </div>
                </div>
            ) : (
                <div>
                    <label
                        htmlFor="signature-upload"
                        style={{
                            display: 'block', border: '2px dashed #cbd5e1', borderRadius: 10, padding: 16,
                            textAlign: 'center', cursor: 'pointer', background: '#fafafa', color: '#475569', fontSize: 13,
                        }}
                    >
                        {upload
                            ? <img src={upload} alt="Uploaded signature" style={{ maxWidth: '100%', maxHeight: 120 }} />
                            : 'Choose an image of your signature (PNG or JPEG). A signature on a white or transparent background prints best.'}
                    </label>
                    <input
                        id="signature-upload" type="file" accept="image/png,image/jpeg"
                        aria-label="Signature image" onChange={pick}
                        style={{ position: 'absolute', width: 1, height: 1, opacity: 0 }}
                    />
                    {upload && <p style={{ fontSize: 12, color: '#94a3b8', marginTop: 6 }}>Click the image to choose a different one.</p>}
                </div>
            )}

            {error && <p style={{ color: '#dc2626', fontSize: 13, marginTop: 8 }}>{error}</p>}
        </div>
    );
}
