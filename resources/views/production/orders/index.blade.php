@extends('layouts.app')

@section('title', 'Production Orders')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Production Orders</h1>
        <p class="page-subtitle">Manage manufacturing orders</p>
    </div>
    <a href="{{ route('production.orders.create') }}" class="btn-primary">
        + New Order
    </a>
</div>

<div class="table-wrapper overflow-x-auto">
    <table class="table-base">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Product</th>
                <th class="text-right">Qty to Produce</th>
                <th class="text-right">Produced</th>
                <th>Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
            <tr>
                <td class="font-mono text-gray-700">{{ $order->order_number ?? 'PRD-' . str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</td>
                <td class="font-medium text-gray-800">{{ $order->product->item_name ?? '' }}</td>
                <td class="text-right text-gray-700">{{ number_format($order->quantity_to_produce, 2) }}</td>
                <td class="text-right text-gray-700">{{ number_format($order->quantity_produced ?? 0, 2) }}</td>
                <td>{{ $order->production_date ? \Carbon\Carbon::parse($order->production_date)->format('d M Y') : '' }}</td>
                <td>
                    @php
                        $badgeClass = match($order->status ?? 'pending') {
                            'pending' => 'badge-yellow',
                            'in_progress' => 'badge-blue',
                            'completed' => 'badge-green',
                            'cancelled' => 'badge-red',
                            default => 'badge-gray',
                        };
                    @endphp
                    <span class="{{ $badgeClass }}">{{ ucwords(str_replace('_', ' ', $order->status ?? 'pending')) }}</span>
                </td>
                <td>
                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="{{ route('production.orders.show', $order) }}" class="btn-primary btn-sm">View</a>
                        @if($order->status === 'pending')
                            <form action="{{ route('production.orders.start', $order) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn-primary btn-sm">Start</button>
                            </form>
                        @endif
                        @if($order->status === 'in_progress')
                            <form action="{{ route('production.orders.complete', $order) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn-success btn-sm">Complete</button>
                            </form>
                        @endif
                        <a href="{{ route('production.orders.edit', $order) }}" class="btn-secondary btn-sm">Edit</a>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-400">No production orders found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($orders->hasPages())
<div class="mt-4">{{ $orders->links() }}</div>
@endif
@endsection
