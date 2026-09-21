import { Link } from 'react-router-dom';
import { money, qty } from '../../../currency';

const isLow = (line) => line.minimum_stock_level > 0 && line.quantity < line.minimum_stock_level;

/**
 * One section of a warehouse's stock.
 *
 * `tone` tints the section's header strip so the two tables are told apart at a
 * glance rather than reading as one long white sheet — blue for what is bought,
 * green for what is made, matching the badges the item pages already use for
 * those categories.
 */
export default function WarehouseStockTable({ title, lines, emptyMessage, tone }) {
    const total = lines.reduce((sum, line) => sum + Number(line.total_value ?? 0), 0);
    const low = lines.filter(isLow).length;

    return (
        <div
            style={{
                background: '#fff', border: '1px solid #e2e8f0', borderRadius: 14,
                overflow: 'hidden', marginBottom: 20, boxShadow: '0 1px 2px rgba(15,23,42,.04)',
            }}
        >
            <div style={{
                display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12,
                padding: '12px 16px', background: tone.band, borderBottom: `1px solid ${tone.border}`,
            }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 9, minWidth: 0 }}>
                    <span style={{ width: 9, height: 9, borderRadius: 9, background: tone.dot, flexShrink: 0 }} />
                    <h2 style={{ fontSize: 13.5, fontWeight: 700, color: tone.text, letterSpacing: '.01em' }}>{title}</h2>
                    <span style={{ fontSize: 12, color: tone.muted }}>
                        {lines.length === 1 ? '1 item' : `${lines.length} items`}
                    </span>
                    {low > 0 && (
                        <span className="badge-red" style={{ fontSize: 10.5 }}>{low} low</span>
                    )}
                </div>
                <div style={{ fontSize: 12.5, fontWeight: 700, color: tone.text, whiteSpace: 'nowrap' }}>
                    {money(total)}
                </div>
            </div>

            <div className="overflow-x-auto">
                <table className="table-base">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Section</th>
                            <th>UOM</th>
                            <th className="text-right">Quantity</th>
                            <th className="text-right">Cost Price</th>
                            <th className="text-right">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        {lines.length === 0 && (
                            <tr>
                                <td colSpan={7} className="px-4 py-6 text-center text-gray-400">{emptyMessage}</td>
                            </tr>
                        )}

                        {lines.map((line) => (
                            <tr key={line.id} style={isLow(line) ? { background: '#fef2f2' } : undefined}>
                                <td className="font-mono text-gray-700">
                                    <Link to="/app/inventory/items" className="text-blue-600 hover:underline">
                                        {line.item_code}
                                    </Link>
                                </td>
                                <td className="font-medium text-gray-800">
                                    {line.item_name}
                                    {!line.is_active && (
                                        <span className="badge-gray" style={{ marginLeft: 8 }}>Inactive</span>
                                    )}
                                </td>
                                <td className="text-gray-600">{line.item_category_name ?? '—'}</td>
                                <td className="text-gray-600">{line.unit_of_measure}</td>
                                <td
                                    className="text-right font-semibold"
                                    style={isLow(line) ? { color: '#dc2626' } : undefined}
                                >
                                    {qty(line.quantity)}
                                </td>
                                <td className="text-right text-gray-600">{money(line.cost_price)}</td>
                                <td className="text-right font-medium text-gray-800">{money(line.total_value)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

/** Blue for what is bought, green for what is made, amber for what is mid-way. */
export const TONES = {
    raw_material: { band: '#eff6ff', border: '#dbeafe', dot: '#3b82f6', text: '#1e40af', muted: '#60a5fa' },
    finished_good: { band: '#ecfdf5', border: '#d1fae5', dot: '#10b981', text: '#065f46', muted: '#34d399' },
    wip: { band: '#fffbeb', border: '#fef3c7', dot: '#f59e0b', text: '#92400e', muted: '#fbbf24' },
};
