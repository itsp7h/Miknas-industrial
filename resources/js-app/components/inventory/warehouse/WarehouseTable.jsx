import { Link } from 'react-router-dom';
import mapLink from '../../map/mapLink';

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
                            <td className="font-medium text-gray-800">
                                <Link to={`/app/inventory/warehouses/${warehouse.id}`} className="text-blue-600 hover:underline">
                                    {warehouse.name}
                                </Link>
                            </td>
                            <td>
                                <div>{warehouse.location || '—'}</div>
                                {/* The pin is the point of the map picker; a
                                    row that has one says so and opens it. */}
                                {mapLink(warehouse.latitude, warehouse.longitude) && (
                                    <a
                                        href={mapLink(warehouse.latitude, warehouse.longitude)}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-blue-600 hover:underline"
                                        style={{ fontSize: 11.5 }}
                                    >
                                        📍 View on map
                                    </a>
                                )}
                            </td>
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
