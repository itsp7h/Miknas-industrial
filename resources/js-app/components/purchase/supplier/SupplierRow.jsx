import Button from '../../ui/Button';

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
                    <Button variant="link" onClick={() => onEdit(row)}>Edit</Button>
                    <Button variant="link-danger" onClick={() => onDelete(row)}>Delete</Button>
                </div>
            ),
        },
    ];
}
