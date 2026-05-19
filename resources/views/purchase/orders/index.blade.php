@extends('layouts.app')

@section('title', 'Purchase Orders')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Purchase Orders</h1>
        <p class="page-subtitle">Manage all purchase orders</p>
    </div>
    <a href="{{ route('purchase.orders.create') }}" class="btn-primary">
        + New PO
    </a>
</div>

<div class="table-wrapper overflow-x-auto">
    <table class="table-base">
        <thead>
            <tr>
                <th>PO #</th>
                <th>Supplier</th>
                <th>Date</th>
                <th>Expected Delivery</th>
                <th class="text-right">Total Amount</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
            <tr>
                <td class="font-mono text-gray-700">{{ $order->po_number ?? 'PO-' . str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</td>
                <td class="text-gray-800">{{ $order->supplier->name ?? '' }}</td>
                <td>{{ $order->po_date ? \Carbon\Carbon::parse($order->po_date)->format('d M Y') : '' }}</td>
                <td>{{ $order->expected_delivery_date ? \Carbon\Carbon::parse($order->expected_delivery_date)->format('d M Y') : '' }}</td>
                <td class="text-right font-medium text-gray-800">{{ number_format($order->total_amount, 2) }}</td>
                <td>
                    @php
                        $badgeClass = match($order->status ?? 'draft') {
                            'draft' => 'badge-gray',
                            'sent' => 'badge-blue',
                            'received' => 'badge-green',
                            'cancelled' => 'badge-red',
                            default => 'badge-gray',
                        };
                    @endphp
                    <span class="{{ $badgeClass }}">{{ ucfirst($order->status ?? 'draft') }}</span>
                </td>
                <td>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('purchase.orders.show', $order) }}" class="btn-primary btn-sm">View</a>
                        <a href="{{ route('purchase.orders.edit', $order) }}" class="btn-secondary btn-sm">Edit</a>
                        <form action="{{ route('purchase.orders.destroy', $order) }}" method="POST"
                              onsubmit="confirmDelete(this,'Delete this purchase order?','This order will be permanently removed.'); return false;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-400">No purchase orders found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($orders->hasPages())
<div class="mt-4">{{ $orders->links() }}</div>
@endif
@endsection
