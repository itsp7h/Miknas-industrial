import { useRef, useState } from 'react';
import Modal from '../../ui/Modal';
import { useToast } from '../../ui/Toast';
import { readImageFile } from '../../image/readImage';

const DISABLED = { opacity: 0.5, cursor: 'not-allowed' };
const HEADER_BUTTON = { borderColor: '#a5b4fc', color: '#4338ca' };

/** How large each is kept: a logo is often wide, a stamp roughly round. */
const SLOTS = [
    { kind: 'logo', label: 'Logo', box: { maxW: 800, maxH: 400 } },
    { kind: 'stamp', label: 'Stamp', box: { maxW: 600, maxH: 600 } },
];

/**
 * A company's logo and stamp, as buttons on its purple header bar: Upload
 * (a new image replaces the old) and View, which opens the image in a dialog
 * with Remove. View is disabled until there is something to see; without
 * companies.edit, Upload and Remove are disabled with the reason
 * (CLAUDE.md #14) — viewing needs nothing more than the page.
 */
export default function CompanyImages({ company, canEdit, onUpload, onRemove }) {
    const [viewing, setViewing] = useState(null);
    const slot = SLOTS.find((s) => s.kind === viewing);

    return (
        <>
            {SLOTS.map((s) => (
                <UploadAndView key={s.kind} slot={s} company={company} canEdit={canEdit} onUpload={onUpload} onView={setViewing} />
            ))}

            <Modal
                open={!!slot && !!company[viewing]}
                title={slot ? `${company.name} — ${slot.label}` : ''}
                onClose={() => setViewing(null)}
            >
                {slot && company[viewing] && (
                    <ViewImage
                        slot={slot} company={company} canEdit={canEdit}
                        onRemove={async () => { await onRemove(company, slot.kind); setViewing(null); }}
                        onClose={() => setViewing(null)}
                    />
                )}
            </Modal>
        </>
    );
}

function UploadAndView({ slot, company, canEdit, onUpload, onView }) {
    const input = useRef(null);
    const [busy, setBusy] = useState(false);
    const { showToast } = useToast();
    const image = company[slot.kind];
    const noun = slot.label.toLowerCase();

    async function pick(event) {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) return;
        setBusy(true);
        try {
            await onUpload(company, slot.kind, await readImageFile(file, slot.box));
        } catch (err) {
            showToast(err?.errors?.image?.[0] ?? err?.message ?? 'Something went wrong.', 'error');
        } finally {
            setBusy(false);
        }
    }

    return (
        <>
            <input
                ref={input} type="file" accept="image/png,image/jpeg" hidden
                aria-label={`${slot.label} image for ${company.name}`}
                onChange={pick}
            />
            <button
                type="button" className="btn-secondary btn-sm"
                style={{ ...HEADER_BUTTON, ...(canEdit ? {} : DISABLED) }}
                disabled={!canEdit || busy}
                title={canEdit ? (image ? `Replace the ${noun}` : undefined) : `You do not have permission to change a company's ${noun}`}
                onClick={() => input.current?.click()}
            >
                {busy ? 'Saving…' : `Upload ${slot.label}`}
            </button>
            <button
                type="button" className="btn-secondary btn-sm"
                style={{ ...HEADER_BUTTON, ...(image ? {} : DISABLED) }}
                disabled={!image}
                title={image ? undefined : `No ${noun} uploaded yet`}
                onClick={() => onView(slot.kind)}
            >
                View {slot.label}
            </button>
        </>
    );
}

function ViewImage({ slot, company, canEdit, onRemove, onClose }) {
    const [removing, setRemoving] = useState(false);
    const { showToast } = useToast();
    const noun = slot.label.toLowerCase();

    async function remove() {
        setRemoving(true);
        try {
            await onRemove();
        } catch (err) {
            showToast(err?.message ?? 'Something went wrong.', 'error');
            setRemoving(false);
        }
    }

    return (
        <div>
            {/* A checkerboard behind it, so a transparent stamp reads as one. */}
            <div style={{
                display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 16, borderRadius: 8,
                border: '1px solid #e2e8f0', minHeight: 180,
                background: 'repeating-conic-gradient(#f1f5f9 0% 25%, #fff 0% 50%) 0 0 / 16px 16px',
            }}>
                <img
                    src={company[slot.kind]} alt={`${company.name} ${noun}`}
                    style={{ maxWidth: '100%', maxHeight: 320, objectFit: 'contain' }}
                />
            </div>
            <div className="mt-6 flex items-center justify-end gap-3">
                <button
                    type="button" className="btn-danger"
                    style={canEdit ? undefined : DISABLED}
                    disabled={!canEdit || removing}
                    title={canEdit ? undefined : `You do not have permission to change a company's ${noun}`}
                    onClick={remove}
                >
                    {removing ? 'Removing…' : `Remove ${slot.label}`}
                </button>
                <button type="button" className="btn-secondary" onClick={onClose}>Close</button>
            </div>
        </div>
    );
}
