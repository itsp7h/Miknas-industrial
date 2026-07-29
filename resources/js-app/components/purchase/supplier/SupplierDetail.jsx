import Card from '../../ui/Card';

export default function SupplierDetail({ supplier }) {
    return (
        <Card title={supplier.name}>
            <dl className="grid grid-cols-2 gap-4 text-sm text-gray-600">
                <div><dt className="font-medium text-gray-800">Supplier code</dt><dd>{supplier.supplier_code ?? '—'}</dd></div>
                <div><dt className="font-medium text-gray-800">Category</dt><dd>{supplier.category ?? '—'}</dd></div>
                <div><dt className="font-medium text-gray-800">Contact person</dt><dd>{supplier.contact_person ?? '—'}</dd></div>
                <div><dt className="font-medium text-gray-800">Email</dt><dd>{supplier.email ?? '—'}</dd></div>
                <div><dt className="font-medium text-gray-800">Phone</dt><dd>{supplier.phone ?? '—'}</dd></div>
                <div><dt className="font-medium text-gray-800">Credit days</dt><dd>{supplier.credit_days ?? '—'}</dd></div>
                <div><dt className="font-medium text-gray-800">Status</dt><dd>{supplier.is_active ? 'Active' : 'Inactive'}</dd></div>
            </dl>
        </Card>
    );
}
