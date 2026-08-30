import { useState } from 'react';
import Button from '../../ui/Button';

/**
 * Date and item narrowing happen server-side (the movement ledger can be large);
 * free-text search over the returned rows stays client-side as everywhere else.
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
        <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', alignItems: 'flex-end', marginBottom: 12 }}>
            <div>
                <label htmlFor="from_date" className="block text-xs font-medium text-gray-700 mb-1">From</label>
                <input id="from_date" type="date" value={filters.from_date} onChange={(e) => set('from_date', e.target.value)}
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm" />
            </div>
            <div>
                <label htmlFor="to_date" className="block text-xs font-medium text-gray-700 mb-1">To</label>
                <input id="to_date" type="date" value={filters.to_date} onChange={(e) => set('to_date', e.target.value)}
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm" />
            </div>
            <div>
                <label htmlFor="item_id" className="block text-xs font-medium text-gray-700 mb-1">Item</label>
                <select id="item_id" value={filters.item_id} onChange={(e) => set('item_id', e.target.value)}
                    className="border border-gray-300 rounded-md px-3 py-2 text-sm">
                    <option value="">All items</option>
                    {items.map((item) => (
                        <option key={item.id} value={item.id}>{item.item_name}</option>
                    ))}
                </select>
            </div>
            <Button onClick={() => apply()}>Apply</Button>
            <Button variant="secondary" onClick={clear}>Clear</Button>
        </div>
    );
}
