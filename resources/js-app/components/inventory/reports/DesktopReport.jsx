import Card from '../../ui/Card';
import Table from '../../ui/Table';

/**
 * Desktop report frame: title, an optional summary strip, and the shared
 * Table (which brings client-side search and the live count with it).
 */
export default function DesktopReport({ title, summary, columns, rows, loading, emptyMessage, children }) {
    return (
        <Card title={title}>
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
                <p style={{ fontSize: 14, color: '#64748b' }}>{emptyMessage}</p>
            )}

            {!loading && rows.length > 0 && (
                <Table columns={columns} rows={rows} rowKey={(row) => row.id} searchPlaceholder={`Search ${title.toLowerCase()}…`} />
            )}
        </Card>
    );
}
