import ReportTable from './ReportTable';

/**
 * Desktop report frame: page header, an optional summary strip, and the
 * .table-base report table the Blade reports used — which, unlike the shared
 * `Table` primitive, supports per-row emphasis.
 */
export default function DesktopReport({
    title, subtitle, summary, columns, rows, loading,
    emptyMessage, noun = 'lines', rowClassName, footer, children, emptyTone,
}) {
    return (
        <div>
            <div className="mb-6">
                <h1 className="page-title">{title}</h1>
                {subtitle && <p className="page-subtitle">{subtitle}</p>}
            </div>

            {children}

            {summary && (
                <div
                    style={{
                        display: 'flex', gap: 24, padding: '10px 12px', marginBottom: 12,
                        background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 8,
                    }}
                >
                    {summary.map((entry) => (
                        <div key={entry.label}>
                            <div style={{ fontSize: 11, textTransform: 'uppercase', letterSpacing: '.05em', color: '#64748b' }}>
                                {entry.label}
                            </div>
                            <div style={{ fontSize: 18, fontWeight: 700, color: entry.tone ?? '#0f172a' }}>{entry.value}</div>
                        </div>
                    ))}
                </div>
            )}

            {loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}

            {!loading && rows.length === 0 && (
                <p style={{ fontSize: 14, color: emptyTone ?? '#64748b', fontWeight: emptyTone ? 500 : undefined }}>
                    {emptyMessage}
                </p>
            )}

            {!loading && rows.length > 0 && (
                <ReportTable
                    columns={columns}
                    rows={rows}
                    noun={noun}
                    rowClassName={rowClassName}
                    footer={footer}
                    emptyMessage={emptyMessage}
                />
            )}
        </div>
    );
}
