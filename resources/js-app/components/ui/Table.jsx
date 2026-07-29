import { useMemo, useState } from 'react';

export default function Table({ columns, rows, rowKey, searchPlaceholder }) {
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
            <div className="flex items-center justify-between mb-3">
                <input
                    type="text"
                    placeholder={searchPlaceholder}
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm w-full"
                    style={{ maxWidth: '20rem' }}
                />
                <span className="text-sm text-gray-500 ml-4 whitespace-nowrap">
                    {query ? `${filtered.length} of ${rows.length}` : rows.length}
                </span>
            </div>

            {filtered.length === 0 ? (
                <p className="text-sm text-gray-500 py-6 text-center">No results.</p>
            ) : (
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-gray-500 border-b border-gray-200">
                            {columns.map((col) => (
                                <th key={col.key} className="py-2 pr-4 font-medium">{col.label}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.map((row) => (
                            <tr key={rowKey(row)} className="border-b border-gray-100">
                                {columns.map((col) => (
                                    <td key={col.key} className="py-2 pr-4">
                                        {col.render ? col.render(row) : row[col.key]}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </div>
    );
}
