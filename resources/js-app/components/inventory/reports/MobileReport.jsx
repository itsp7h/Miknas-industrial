import { useMemo, useState } from 'react';

/**
 * Mobile report frame: a card list instead of a wide table, with the same
 * client-side search + live count contract as every other mobile list
 * (CLAUDE.md gotcha #6).
 */
export default function MobileReport({
    title, subtitle, summary, rows, loading, searchKeys,
    renderCard, cardClassName, emptyMessage, children, emptyTone,
}) {
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return rows;
        return rows.filter((row) =>
            searchKeys.some((key) => String(row[key] ?? '').toLowerCase().includes(q))
        );
    }, [rows, query, searchKeys]);

    const noun = title.toLowerCase();

    return (
        <div>
            <div style={{ marginBottom: 12 }}>
                <h1 className="page-title">{title}</h1>
                {subtitle && <p className="page-subtitle">{subtitle}</p>}
            </div>

            {children}

            {summary && (
                <div style={{ display: 'flex', gap: 16, flexWrap: 'wrap', padding: 12, marginBottom: 12, background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 8 }}>
                    {summary.map((entry) => (
                        <div key={entry.label}>
                            <div style={{ fontSize: 11, textTransform: 'uppercase', letterSpacing: '.05em', color: '#64748b' }}>
                                {entry.label}
                            </div>
                            <div style={{ fontSize: 17, fontWeight: 700, color: entry.tone ?? '#0f172a' }}>{entry.value}</div>
                        </div>
                    ))}
                </div>
            )}

            <div style={{ marginBottom: 12 }}>
                <input
                    type="search"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder={`Search ${noun}…`}
                    aria-label={`Search ${noun}`}
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                />
                <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    {query ? `${filtered.length} of ${rows.length} lines` : `${rows.length} lines`}
                </div>
            </div>

            {loading && <p style={{ fontSize: 14, color: '#64748b' }}>Loading…</p>}

            {!loading && filtered.length === 0 && (
                <p style={{
                    fontSize: 14,
                    color: query ? '#64748b' : (emptyTone ?? '#64748b'),
                    fontWeight: !query && emptyTone ? 500 : undefined,
                }}>
                    {query ? `No ${noun} match that search.` : emptyMessage}
                </p>
            )}

            {!loading && filtered.map((row) => (
                <div
                    key={row.id}
                    className={cardClassName ? cardClassName(row) : undefined}
                    style={{ border: '1px solid #e2e8f0', borderRadius: 12, padding: 12, marginBottom: 8, background: '#fff' }}
                >
                    {renderCard(row)}
                </div>
            ))}
        </div>
    );
}
