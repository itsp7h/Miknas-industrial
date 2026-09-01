import { categoryBadgeClass, categoryLabel, num } from './itemStyles';

/** The Blade inventory-items table: badged category and status, right-aligned figures. */
export default function ItemTable({ items, onEdit, onDelete }) {
    return (
        <div className="table-wrapper overflow-x-auto">
            <table className="table-base">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>UOM</th>
                        <th className="text-right">Min Stock</th>
                        <th className="text-right">Cost Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {items.length === 0 && (
                        <tr>
                            <td colSpan={8} className="px-4 py-8 text-center text-gray-400">No items found.</td>
                        </tr>
                    )}

                    {items.map((item) => (
                        <tr key={item.id}>
                            <td className="font-mono text-gray-700">{item.item_code}</td>
                            <td className="font-medium text-gray-800">{item.item_name}</td>
                            <td>
                                <span className={categoryBadgeClass(item.category)}>
                                    {categoryLabel(item.category)}
                                </span>
                            </td>
                            <td>{item.unit_of_measure}</td>
                            <td className="text-right">{num(item.minimum_stock_level)}</td>
                            <td className="text-right text-gray-800">{num(item.cost_price)}</td>
                            <td>
                                <span className={item.is_active ? 'badge-green' : 'badge-gray'}>
                                    {item.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </td>
                            <td>
                                <div className="flex items-center gap-2">
                                    <button type="button" onClick={() => onEdit(item)} className="btn-secondary btn-sm">Edit</button>
                                    <button type="button" onClick={() => onDelete(item)} className="btn-danger btn-sm">Delete</button>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
