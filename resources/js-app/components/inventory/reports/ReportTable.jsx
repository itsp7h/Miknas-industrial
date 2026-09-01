import { useMemo, useState } from 'react';

/**
 * The report table the Blade reports used: .table-base chrome, right-alignable
 * columns, and — unlike the shared `Table` primitive — per-row emphasis, which
 * the stock summary needs to paint a below-minimum line red.
 *
 * Carries its own search box and live count, matching the rest of the app.
 */
export default function ReportTable({
    columns,
    rows,
    noun = 'rows',
    rowClassName,
    emptyMessage = 'No data available.',
}) {
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return rows;

        return rows.filter((row) =>
            columns.some((col) => String(row[col.key] ?? '').toLowerCase().includes(q))
        );
    }, [rows, query, columns]);

    return (
        <div>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12, gap: 12 }}>
                <div style={{ position: 'relative' }}>
                    <svg
                        style={{ position: 'absolute', left: 11, top: '50%', transform: 'translateY(-50%)', color: '#94a3b8', pointerEvents: 'none' }}
                        width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                    </svg>
                    <input
                        type="text"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={`Search ${noun}…`}
                        aria-label={`Search ${noun}`}
                        autoComplete="off"
                        style={{ padding: '8px 14px 8px 34px', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13.5, width: 340, outline: 'none' }}
                    />
                </div>
                <div style={{ fontSize: 12.5, color: '#94a3b8', whiteSpace: 'nowrap' }}>
                    {query ? `${filtered.length} of ${rows.length} ${noun}` : `${rows.length} ${noun}`}
                </div>
            </div>

            <div className="table-wrapper overflow-x-auto">
                <table className="table-base">
                    <thead>
                        <tr>
                            {columns.map((col) => (
                                <th key={col.key} className={col.align === 'right' ? 'text-right' : undefined}>
                                    {col.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.length === 0 && (
                            <tr>
                                <td colSpan={columns.length} className="px-4 py-8 text-center text-gray-400">
                                    {query ? `No ${noun} match that search.` : emptyMessage}
                                </td>
                            </tr>
                        )}

                        {filtered.map((row) => (
                            <tr key={row.id} className={rowClassName ? rowClassName(row) : undefined}>
                                {columns.map((col) => (
                                    <td
                                        key={col.key}
                                        className={[
                                            col.align === 'right' ? 'text-right' : '',
                                            col.cellClassName ? col.cellClassName(row) : '',
                                        ].filter(Boolean).join(' ') || undefined}
                                    >
                                        {col.render ? col.render(row) : row[col.key]}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
