import { useState } from 'react';

/**
 * The Blade filter bar: a card holding From / To / Item and a blue Filter
 * button, using the shared .card / .form-* / .btn-* classes from
 * resources/css/app.css.
 *
 * Date and item narrowing happen server-side (the movement ledger can be
 * large); free-text search over the returned rows stays client-side as
 * everywhere else.
 */
export default function MovementFilters({ items, onApply }) {
    const [filters, setFilters] = useState({ from_date: '', to_date: '', item_id: '' });

    function set(name, value) {
        setFilters((prev) => ({ ...prev, [name]: value }));
    }

    function apply(next = filters) {
        const query = Object.entries(next)
            .filter(([, value]) => value !== '')
            .map(([key, value]) => `${key}=${encodeURIComponent(value)}`)
            .join('&');
        onApply(query ? `?${query}` : '');
    }

    function clear() {
        const cleared = { from_date: '', to_date: '', item_id: '' };
        setFilters(cleared);
        apply(cleared);
    }

    return (
        <div className="card card-body mb-6">
            <div className="flex flex-wrap items-end gap-4">
                <div>
                    <label htmlFor="from_date" className="form-label">From Date</label>
                    <input
                        id="from_date" type="date" className="form-input"
                        value={filters.from_date}
                        onChange={(e) => set('from_date', e.target.value)}
                    />
                </div>
                <div>
                    <label htmlFor="to_date" className="form-label">To Date</label>
                    <input
                        id="to_date" type="date" className="form-input"
                        value={filters.to_date}
                        onChange={(e) => set('to_date', e.target.value)}
                    />
                </div>
                <div>
                    <label htmlFor="item_id" className="form-label">Item (optional)</label>
                    <select
                        id="item_id" className="form-select"
                        value={filters.item_id}
                        onChange={(e) => set('item_id', e.target.value)}
                    >
                        <option value="">All Items</option>
                        {items.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.item_code ? `${item.item_code} - ` : ''}{item.item_name}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="flex items-center gap-2">
                    <button type="button" onClick={() => apply()} className="btn-primary">Filter</button>
                    <button type="button" onClick={clear} className="btn btn-secondary">Clear</button>
                </div>
            </div>
        </div>
    );
}
