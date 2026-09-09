import { num } from './useBomList';

/**
 * One product's material requirements: a blue header naming the product and its
 * code, then the lines. Blade's index built exactly this by opening a new card
 * each time the product changed as it walked an ordered list.
 */
export default function BomProductCard({ group, onEdit, onDelete }) {
    return (
        <div className="table-wrapper overflow-hidden mb-4">
            <div className="px-6 py-3 bg-blue-50 border-b border-blue-100 flex items-center justify-between">
                <h3 className="font-semibold text-blue-800 text-sm">{group.productName}</h3>
                <span className="text-xs text-blue-500">{group.productCode}</span>
            </div>
            <table className="table-base">
                <thead>
                    <tr>
                        <th>Raw Material</th>
                        <th className="text-right">Qty Required</th>
                        <th>UOM</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {group.lines.map((line) => (
                        <tr key={line.id}>
                            <td className="text-gray-800">{line.raw_material_name ?? ''}</td>
                            <td className="text-right text-gray-700">{num(line.quantity_required)}</td>
                            <td className="text-gray-500">{line.unit_of_measure}</td>
                            <td>
                                <div className="flex items-center gap-2">
                                    <button type="button" onClick={() => onEdit(line)} className="btn-secondary btn-sm">Edit</button>
                                    <button type="button" onClick={() => onDelete(line)} className="btn-danger btn-sm">Delete</button>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
