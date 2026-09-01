/** The Blade warehouses table: badged status, btn-sm row actions. */
export default function WarehouseTable({ warehouses, onEdit, onDelete }) {
    return (
        <div className="table-wrapper overflow-x-auto">
            <table className="table-base">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {warehouses.length === 0 && (
                        <tr>
                            <td colSpan={5} className="px-4 py-8 text-center text-gray-400">No warehouses found.</td>
                        </tr>
                    )}

                    {warehouses.map((warehouse) => (
                        <tr key={warehouse.id}>
                            <td className="font-mono text-gray-700">{warehouse.code}</td>
                            <td className="font-medium text-gray-800">{warehouse.name}</td>
                            <td>{warehouse.location || '—'}</td>
                            <td>
                                <span className={warehouse.is_active ? 'badge-green' : 'badge-gray'}>
                                    {warehouse.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </td>
                            <td>
                                <div className="flex items-center gap-2">
                                    <button type="button" onClick={() => onEdit(warehouse)} className="btn-secondary btn-sm">Edit</button>
                                    <button type="button" onClick={() => onDelete(warehouse)} className="btn-danger btn-sm">Delete</button>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
