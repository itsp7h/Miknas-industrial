import { useRef, useState } from 'react';
import Modal from '../../ui/Modal';
import Button from '../../ui/Button';

const PROMPT = 'Click to choose or drag & drop your file';

/**
 * The Blade import modal, drag-and-drop and all — the React page had replaced it
 * with a bare file input, losing both the drop target and the note explaining
 * which spreadsheet formats are understood.
 */
export default function ItemImportModal({ open, onClose, onImport }) {
    const inputRef = useRef(null);
    const [file, setFile] = useState(null);
    const [dragging, setDragging] = useState(false);
    const [busy, setBusy] = useState(false);

    function reset() {
        setFile(null);
        setDragging(false);
        if (inputRef.current) inputRef.current.value = '';
    }

    function handleClose() {
        reset();
        onClose();
    }

    function handleDrop(e) {
        e.preventDefault();
        setDragging(false);
        const dropped = e.dataTransfer.files?.[0];
        if (dropped) setFile(dropped);
    }

    async function submit(e) {
        e.preventDefault();
        if (!file) return;
        setBusy(true);
        try {
            await onImport(file);
            reset();
        } finally {
            setBusy(false);
        }
    }

    return (
        <Modal open={open} title="Import Items from Excel" onClose={handleClose}>
            <form onSubmit={submit}>
                <label
                    htmlFor="item-import-file"
                    onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
                    onDragLeave={() => setDragging(false)}
                    onDrop={handleDrop}
                    className={`flex flex-col items-center justify-center gap-3 w-full border-2 border-dashed rounded-xl p-8 cursor-pointer transition-colors ${
                        dragging ? 'border-blue-500 bg-blue-50' : 'border-slate-300 hover:border-blue-400 hover:bg-blue-50'
                    }`}
                >
                    <svg width="36" height="36" fill="none" stroke="#94a3b8" viewBox="0 0 24 24">
                        <path
                            strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5"
                            d="M9 13h6M12 10v6m-7 4h14a2 2 0 002-2V7a2 2 0 00-2-2h-5.586a1 1 0 01-.707-.293l-1.414-1.414A1 1 0 0010.586 3H5a2 2 0 00-2 2v14a2 2 0 002 2z"
                        />
                    </svg>
                    <div className="text-center">
                        <p className="text-sm font-medium text-slate-700">{file ? file.name : PROMPT}</p>
                        <p className="text-xs text-slate-400 mt-1">Accepts: .xlsx, .xls — max 10 MB</p>
                    </div>
                    <input
                        id="item-import-file"
                        ref={inputRef}
                        type="file"
                        accept=".xlsx,.xls"
                        className="sr-only"
                        aria-label="Import Excel"
                        onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                    />
                </label>

                <div className="mt-4 p-3 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-700 flex gap-2">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" className="flex-shrink-0 mt-0.5">
                        <path
                            strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <span>
                        Supports the <strong>Forkoll inventory format</strong> and the{' '}
                        <strong>standard item template</strong>. Categories are auto-detected.
                        Items marked &quot;Not in Use&quot; are imported as inactive. Duplicate names are skipped.
                    </span>
                </div>

                <div className="flex items-center justify-end gap-3 mt-5">
                    <Button variant="secondary" onClick={handleClose}>Cancel</Button>
                    <Button type="submit" loading={busy} disabled={!file}>Import</Button>
                </div>
            </form>
        </Modal>
    );
}
