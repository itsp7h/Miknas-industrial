const ICONS = {
    pdf: ['M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z', 'M9 13h6M9 17h4'],
    download: ['M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4'],
    upload: ['M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12'],
};

const Icon = ({ name }) => (
    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        {ICONS[name].map((d) => (
            <path key={d} strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={d} />
        ))}
    </svg>
);

/**
 * Export PDF / Template / Import Excel / + Add Item, using the Blade classes.
 *
 * `stacked` + `showAdd={false}` is how the mobile page renders these inside its
 * "Actions" sheet — that page deliberately keeps the header to Add plus one
 * Actions button rather than four buttons competing for a phone's width.
 */
export default function ItemToolbar({ onImportClick, onCreate, stacked = false, showAdd = true }) {
    return (
        <div
            className={stacked ? undefined : 'flex items-center gap-2 flex-wrap'}
            style={stacked ? { display: 'flex', flexDirection: 'column', gap: 8, width: '100%' } : undefined}
        >
            <a href="/api/v1/inventory/items/export-pdf" className="btn btn-secondary" title="Export items as PDF"
               style={stacked ? { justifyContent: 'center' } : undefined}>
                <Icon name="pdf" /> Export PDF
            </a>
            <a href="/api/v1/inventory/items/template" className="btn btn-secondary" title="Download Excel import template"
               style={stacked ? { justifyContent: 'center' } : undefined}>
                <Icon name="download" /> Template
            </a>
            <button type="button" onClick={onImportClick} className="btn btn-success" title="Import items from Excel"
                    style={stacked ? { justifyContent: 'center' } : undefined}>
                <Icon name="upload" /> Import Excel
            </button>
            {showAdd && (
                <button type="button" onClick={onCreate} className="btn-primary">+ Add Item</button>
            )}
        </div>
    );
}
