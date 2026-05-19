@extends('layouts.app')

@section('title', 'Sales Orders')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Sales Orders</h1>
        <p class="page-subtitle">Manage customer orders</p>
    </div>
    <a href="{{ route('sales.orders.create') }}" class="btn-primary">
        + New Order
    </a>
</div>

<div class="table-wrapper overflow-x-auto">
    <table class="table-base">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Date</th>
                <th class="text-right">Total</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
            <tr>
                <td class="font-mono text-gray-700">{{ $order->order_number ?? 'SO-' . str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</td>
                <td class="font-medium text-gray-800">{{ $order->customer->name ?? '' }}</td>
                <td>{{ $order->order_date ? \Carbon\Carbon::parse($order->order_date)->format('d M Y') : '' }}</td>
                <td class="text-right font-medium text-gray-800">{{ number_format($order->total_amount, 2) }}</td>
                <td>
                    @php
                        $badgeClass = match($order->status ?? 'draft') {
                            'draft' => 'badge-gray',
                            'confirmed' => 'badge-blue',
                            'dispatched' => 'badge-violet',
                            'invoiced' => 'badge-green',
                            'cancelled' => 'badge-red',
                            default => 'badge-gray',
                        };
                    @endphp
                    <span class="{{ $badgeClass }}">{{ ucfirst($order->status ?? 'draft') }}</span>
                </td>
                <td>
                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="{{ route('sales.orders.show', $order) }}" class="btn-primary btn-sm">View</a>
                        @if($order->status === 'draft')
                        <form action="{{ route('sales.orders.confirm', $order) }}" method="POST">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn-primary btn-sm">Confirm</button>
                        </form>
                        @endif
                        <a href="{{ route('sales.orders.edit', $order) }}" class="btn-secondary btn-sm">Edit</a>
                        <form action="{{ route('sales.orders.destroy', $order) }}" method="POST"
                              onsubmit="confirmDelete(this,'Delete this sales order?','This order and its items will be permanently removed.'); return false;">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-400">No sales orders found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($orders->hasPages())
<div class="mt-4">{{ $orders->links() }}</div>
@endif
@endsection
