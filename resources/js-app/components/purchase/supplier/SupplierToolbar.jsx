const ICONS = {
    pdf: ['M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z', 'M9 13h6M9 17h4'],
    download: ['M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4'],
    upload: ['M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12'],
    plus: ['M12 4v16m8-8H4'],
    trash: ['M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16'],
};

const Icon = ({ name, size = 15, width = 2 }) => (
    <svg width={size} height={size} fill="none" stroke="currentColor" viewBox="0 0 24 24">
        {ICONS[name].map((d) => (
            <path key={d} strokeLinecap="round" strokeLinejoin="round" strokeWidth={width} d={d} />
        ))}
    </svg>
);

/**
 * Export PDF / Template / Import Excel / Add Supplier / Delete All, using the same
 * `.btn-*` classes from resources/css/app.css that the Blade page used — the
 * React shell loads that stylesheet, so the buttons are identical rather than
 * re-styled by hand.
 */
export default function SupplierToolbar({
    fileInputRef, onImport, onCreate, onDeleteAll,
    canDeleteAll = false, supplierCount = 0, compact = false,
}) {
    // Shown to everyone and disabled for those who may not use it, rather
    // than hidden: a greyed button with a reason on it tells someone the
    // capability exists and who to ask, where a missing one tells them
    // nothing. The API checks the square regardless.
    const nothingToDelete = supplierCount === 0;
    const deleteAllTitle = !canDeleteAll
        ? 'You do not have permission to delete every supplier. Ask an Admin.'
        : nothingToDelete ? 'There are no suppliers to delete.' : 'Delete every supplier';
    return (
        <div
            className="flex items-center gap-2 flex-wrap"
            style={compact ? { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8, width: '100%' } : undefined}
        >
            <a href="/api/v1/purchase/suppliers/export-pdf" className="btn btn-secondary" title="Export as PDF">
                <Icon name="pdf" /> Export PDF
            </a>
            <a href="/api/v1/purchase/suppliers/template" className="btn btn-secondary" title="Download import template">
                <Icon name="download" /> Template
            </a>
            {/* A label, not a button: it wraps the hidden file input. */}
            <label className="btn btn-success" style={{ cursor: 'pointer', margin: 0 }}>
                <Icon name="upload" /> Import Excel
                <input
                    ref={fileInputRef}
                    type="file"
                    accept=".xlsx,.xls"
                    style={{ display: 'none' }}
                    onChange={onImport}
                    aria-label="Import Excel"
                />
            </label>
            <button type="button" onClick={onCreate} className="btn btn-primary">
                <Icon name="plus" width={2.5} size={14} /> Add Supplier
            </button>
            <button
                type="button"
                onClick={onDeleteAll}
                className="btn btn-danger"
                disabled={!canDeleteAll || nothingToDelete}
                title={deleteAllTitle}
                style={(!canDeleteAll || nothingToDelete)
                    ? { opacity: 0.5, cursor: 'not-allowed' }
                    : undefined}
            >
                <Icon name="trash" /> Delete All
            </button>
        </div>
    );
}
