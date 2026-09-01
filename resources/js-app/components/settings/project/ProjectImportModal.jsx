import { useRef, useState } from 'react';
import Modal from '../../ui/Modal';
import { DownloadIcon } from './icons';

const PROMPT = 'Click to browse, or drop your Excel file here';

/** Blade's import modal, drop target and format note included. */
export default function ProjectImportModal({ open, onClose, onImport }) {
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

    const border = dragging ? '#6366f1' : (file ? '#22c55e' : '#cbd5e1');
    const background = dragging ? '#eef2ff' : (file ? '#f0fdf4' : '#f8fafc');

    return (
        <Modal open={open} title="Import from Excel" onClose={handleClose}>
            <p style={{ fontSize: 12, color: '#64748b', margin: '-8px 0 14px' }}>
                Companies, projects and departments from .xlsx / .xls
            </p>

            <form onSubmit={submit}>
                <label
                    htmlFor="project-import-file"
                    onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
                    onDragLeave={() => setDragging(false)}
                    onDrop={(e) => {
                        e.preventDefault();
                        setDragging(false);
                        const dropped = e.dataTransfer.files?.[0];
                        if (dropped) setFile(dropped);
                    }}
                    style={{
                        display: 'block', border: `2px ${file ? 'solid' : 'dashed'} ${border}`, borderRadius: 12,
                        padding: '2rem', textAlign: 'center', cursor: 'pointer', background,
                        transition: 'border-color .15s, background .15s',
                    }}
                >
                    <svg width="32" height="32" fill="none" stroke={file ? '#22c55e' : '#94a3b8'} viewBox="0 0 24 24" style={{ margin: '0 auto 10px', display: 'block' }}>
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l4-4m0 0l4 4m-4-4v12" />
                    </svg>
                    <p style={{ fontSize: 14, color: '#475569', margin: 0, fontWeight: 500 }}>{file ? file.name : PROMPT}</p>
                    <p style={{ fontSize: 12, color: '#94a3b8', margin: '4px 0 0' }}>
                        {file ? `${(file.size / 1024).toFixed(1)} KB — ready to import` : '.xlsx or .xls — max 10 MB'}
                    </p>
                    <input
                        id="project-import-file" ref={inputRef} type="file" accept=".xlsx,.xls"
                        className="sr-only" aria-label="Import Excel"
                        onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                    />
                </label>

                <div style={{ marginTop: '1rem', background: '#f0f9ff', border: '1px solid #bae6fd', borderRadius: 8, padding: '0.75rem 1rem' }}>
                    <p style={{ fontSize: 12, color: '#0369a1', margin: 0, lineHeight: 1.7 }}>
                        <strong>Expected format:</strong><br />
                        <strong>Projects</strong> tab — <em>Company Name</em> | <em>Project Name</em><br />
                        Company is created automatically if it doesn&apos;t exist. Duplicate project names are skipped.
                    </p>
                </div>

                <div className="mt-5" style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12 }}>
                    <a
                        href="/api/v1/settings/projects/template"
                        style={{ fontSize: 13, color: '#3b82f6', textDecoration: 'none', display: 'flex', alignItems: 'center', gap: 5 }}
                    >
                        <DownloadIcon />
                        Download template
                    </a>
                    <div style={{ display: 'flex', gap: 8 }}>
                        <button type="button" onClick={handleClose} className="btn-secondary">Cancel</button>
                        <button type="submit" className="btn-primary" disabled={!file || busy} style={{ minWidth: 100 }}>
                            {busy ? 'Importing…' : 'Import'}
                        </button>
                    </div>
                </div>
            </form>
        </Modal>
    );
}
