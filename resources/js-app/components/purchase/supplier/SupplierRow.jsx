export function supplierTableColumns({ onEdit, onDelete }) {
    return [
        { key: 'supplier_code', label: 'Code' },
        { key: 'name', label: 'Name' },
        { key: 'category', label: 'Category' },
        { key: 'email', label: 'Email' },
        {
            key: 'is_active',
            label: 'Status',
            render: (row) => (row.is_active ? 'Active' : 'Inactive'),
        },
        {
            key: 'actions',
            label: '',
            render: (row) => (
                <div className="flex gap-2">
                    <button className="text-blue-600 text-sm" onClick={() => onEdit(row)}>Edit</button>
                    <button className="text-red-600 text-sm" onClick={() => onDelete(row)}>Delete</button>
                </div>
            ),
        },
    ];
}
