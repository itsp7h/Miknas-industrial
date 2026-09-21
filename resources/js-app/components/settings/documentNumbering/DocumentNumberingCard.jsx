import useDocumentNumbering, { previewFor } from './useDocumentNumbering';

const CARD = {
    background: '#fff', border: '1px solid #e2e8f0', borderRadius: 14,
    padding: 20, maxWidth: 720,
};

const ROW = {
    display: 'grid', gridTemplateColumns: 'minmax(0,1fr) 6.5rem minmax(0,11rem) minmax(0,11rem)',
    gap: 12, alignItems: 'center', padding: '10px 0', borderTop: '1px solid #f1f5f9',
};

const HEAD = {
    fontSize: 10.5, fontWeight: 700, color: '#94a3b8',
    textTransform: 'uppercase', letterSpacing: '.05em',
};

const PREVIEW = {
    fontFamily: 'ui-monospace, SFMono-Regular, Menlo, monospace',
    fontSize: 12.5, fontWeight: 700, color: '#0f172a',
    background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 8,
    padding: '5px 9px', textAlign: 'center',
};

/**
 * Each company's letters in its own document series — the ST in ST-LPO-26-0001
 * and ST-MPR-26-0001.
 *
 * One code per company, since it names the company rather than the paperwork.
 * Only the letters are editable: the document, the two-digit year and the
 * four-digit sequence are the shape of the number, not a preference, and each
 * document counts separately per company per year.
 */
export default function DocumentNumberingCard({ maxWidth }) {
    const n = useDocumentNumbering();

    if (n.loading) {
        return <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>;
    }

    return (
        <div style={{ ...CARD, ...(maxWidth ? { maxWidth } : {}) }}>
            <h2 style={{ fontSize: 14, fontWeight: 700, color: '#0f172a', margin: '0 0 4px' }}>
                Document Numbering
            </h2>
            <p style={{ fontSize: 12.5, color: '#64748b', margin: '0 0 14px' }}>
                Each company numbers its own purchase requests and purchase orders, from
                one code. Both sequences start again at 0001 each year.
            </p>

            <div style={{ ...ROW, borderTop: 'none', paddingTop: 0 }}>
                <span style={HEAD}>Company</span>
                <span style={HEAD}>Code</span>
                <span style={{ ...HEAD, textAlign: 'center' }}>Next MPR</span>
                <span style={{ ...HEAD, textAlign: 'center' }}>Next LPO</span>
            </div>

            {n.companies.map((company) => (
                <div key={company.id} style={ROW}>
                    <span style={{ fontSize: 13, color: '#0f172a', fontWeight: 500 }}>
                        {company.name}
                        {!company.is_active && (
                            <span style={{ fontSize: 11, color: '#94a3b8' }}> · inactive</span>
                        )}
                    </span>
                    <input
                        type="text" className="form-input" maxLength={8}
                        aria-label={`${company.name} document code`}
                        value={n.codes[company.id] ?? ''}
                        onChange={(e) => n.setCode(company.id, e.target.value.toUpperCase())}
                        style={{ width: '100%', textTransform: 'uppercase', fontWeight: 700 }}
                    />
                    <span style={PREVIEW}>
                        {previewFor(n.codes[company.id], company.next_mpr_number, 'MPR')}
                    </span>
                    <span style={PREVIEW}>
                        {previewFor(n.codes[company.id], company.next_number, 'LPO')}
                    </span>
                </div>
            ))}

            {n.companies.length === 0 && (
                <p style={{ fontSize: 13, color: '#94a3b8', padding: '12px 0 0' }}>
                    No companies yet. Add one under Companies &amp; Departments first.
                </p>
            )}

            {n.error && (
                <p role="alert" style={{ color: '#dc2626', fontSize: 12, marginTop: 12 }}>{n.error}</p>
            )}

            {n.companies.length > 0 && (
                <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 16 }}>
                    <button
                        type="button" className="btn-primary"
                        onClick={n.save} disabled={n.saving || !n.dirty}
                    >
                        {n.saving ? 'Saving…' : 'Save'}
                    </button>
                </div>
            )}
        </div>
    );
}
