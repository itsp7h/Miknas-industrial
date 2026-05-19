@extends('layouts.app')

@section('title', 'Customers')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Customers</h1>
        <p class="page-subtitle">Manage your customer directory</p>
    </div>
    <a href="{{ route('sales.customers.create') }}" class="btn-primary">
        + Add Customer
    </a>
</div>

<div class="table-wrapper overflow-x-auto">
    <table class="table-base">
        <thead>
            <tr>
                <th>Name</th>
                <th>Contact</th>
                <th>Email</th>
                <th class="text-right">Credit Limit</th>
                <th class="text-right">Outstanding Balance</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($customers as $customer)
            <tr>
                <td class="font-medium text-gray-800">{{ $customer->name }}</td>
                <td>{{ $customer->contact_person }}</td>
                <td>{{ $customer->email }}</td>
                <td class="text-right text-gray-700">{{ number_format($customer->credit_limit, 2) }}</td>
                <td class="text-right {{ ($customer->outstanding_balance ?? 0) > 0 ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                    {{ number_format($customer->outstanding_balance ?? 0, 2) }}
                </td>
                <td>
                    @if($customer->is_active)
                        <span class="badge-green">Active</span>
                    @else
                        <span class="badge-gray">Inactive</span>
                    @endif
                </td>
                <td>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('sales.customers.edit', $customer) }}" class="btn-secondary btn-sm">Edit</a>
                        <form action="{{ route('sales.customers.destroy', $customer) }}" method="POST"
                              onsubmit="confirmDelete(this,'Delete this customer?','This customer record will be permanently removed.'); return false;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-400">No customers found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($customers->hasPages())
<div class="mt-4">{{ $customers->links() }}</div>
@endif
@endsection
