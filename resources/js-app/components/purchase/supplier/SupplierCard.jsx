import Card from '../../ui/Card';

export default function SupplierCard({ supplier }) {
    return (
        <Card title={supplier.name}>
            <dl className="text-sm text-gray-600 space-y-1">
                <div><dt className="inline font-medium">Code: </dt><dd className="inline">{supplier.supplier_code ?? '—'}</dd></div>
                <div><dt className="inline font-medium">Category: </dt><dd className="inline">{supplier.category ?? '—'}</dd></div>
                <div><dt className="inline font-medium">Email: </dt><dd className="inline">{supplier.email ?? '—'}</dd></div>
                <div><dt className="inline font-medium">Status: </dt><dd className="inline">{supplier.is_active ? 'Active' : 'Inactive'}</dd></div>
            </dl>
        </Card>
    );
}
