import { formatDate, num, typeBadgeClass, typeLabel } from './movementStyles';

/**
 * The Blade stock-movements table. "Reference" now shows the real linked
 * document — the Blade column read a non-existent property and always printed
 * "-". Notes is kept from the React version; it is real data the Blade table
 * never surfaced.
 */
export default function MovementTable({ movements }) {
    return (
        <div className="table-wrapper overflow-x-auto">
            <table className="table-base">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Warehouse</th>
                        <th>Type</th>
                        <th className="text-right">Quantity</th>
                        <th>Reference</th>
                        <th>Notes</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    {movements.length === 0 && (
                        <tr>
                            <td colSpan={7} className="px-4 py-8 text-center text-gray-400">No movements recorded.</td>
                        </tr>
                    )}

                    {movements.map((movement) => (
                        <tr key={movement.id}>
                            <td className="text-gray-800">
                                <span className="font-mono text-xs text-gray-500">{movement.item_code ?? ''}</span>
                                <span className="ml-1">{movement.item_name ?? ''}</span>
                            </td>
                            <td className="text-gray-700">{movement.warehouse_name ?? ''}</td>
                            <td>
                                <span className={typeBadgeClass(movement.type)}>{typeLabel(movement.type)}</span>
                            </td>
                            <td className="text-right font-medium text-gray-800">{num(movement.quantity)}</td>
                            <td className="text-gray-500 text-xs">{movement.reference ?? 'Manual adjustment'}</td>
                            <td className="text-gray-500 text-xs">{movement.notes || '—'}</td>
                            <td>{formatDate(movement.created_at)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
