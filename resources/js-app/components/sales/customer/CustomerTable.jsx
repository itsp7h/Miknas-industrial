import { money } from '../order/statuses';

/**
 * Blade's seven columns. Two details the first React port lost: the outstanding
 * balance goes red and semibold once it is above zero — the whole reason the
 * column is there — and the status is a badge, not the word "Yes".
 */
export default function CustomerTable({ customers, onEdit, onDelete }) {
    return (
        <div className="table-wrapper overflow-x-auto">
            <table className="table-base">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Email</th>
                        <th className="text-right">Credit Limit</th>
                        <th className="text-right">Outstanding Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {customers.length === 0 && (
                        <tr>
                            <td colSpan={7} className="px-4 py-8 text-center text-gray-400">No customers found.</td>
                        </tr>
                    )}

                    {customers.map((customer) => (
                        <tr key={customer.id}>
                            <td className="font-medium text-gray-800">{customer.name}</td>
                            <td>{customer.contact_person}</td>
                            <td>{customer.email}</td>
                            <td className="text-right text-gray-700">{money(customer.credit_limit)}</td>
                            <td className={`text-right ${Number(customer.outstanding_balance ?? 0) > 0 ? 'text-red-600 font-semibold' : 'text-gray-500'}`}>
                                {money(customer.outstanding_balance)}
                            </td>
                            <td>
                                <span className={customer.is_active ? 'badge-green' : 'badge-gray'}>
                                    {customer.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </td>
                            <td>
                                <div className="flex items-center gap-2">
                                    <button type="button" onClick={() => onEdit(customer)} className="btn-secondary btn-sm">Edit</button>
                                    <button type="button" onClick={() => onDelete(customer)} className="btn-danger btn-sm">Delete</button>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
