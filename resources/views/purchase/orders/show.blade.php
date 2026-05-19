@extends('layouts.app')

@section('title', 'Purchase Order')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Purchase Order</h1>
        <p class="page-subtitle">
            <a href="{{ route('purchase.orders.index') }}" class="text-blue-600 hover:underline">Purchase Orders</a>
            / {{ $order->po_number ?? 'PO-' . str_pad($order->id, 5, '0', STR_PAD_LEFT) }}
        </p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('purchase.orders.edit', $order) }}" class="btn-secondary">Edit</a>
        <a href="{{ route('purchase.grns.create', ['purchase_order_id' => $order->id]) }}" class="btn-primary">Create GRN</a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <!-- PO Info -->
    <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Order Information</h2>
        <dl class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">PO Number</dt>
                <dd class="font-semibold text-gray-800 font-mono">{{ $order->po_number ?? 'PO-' . str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Status</dt>
                <dd>
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
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">PO Date</dt>
                <dd class="font-medium text-gray-800">{{ $order->po_date ? \Carbon\Carbon::parse($order->po_date)->format('d M Y') : '-' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Expected Delivery</dt>
                <dd class="font-medium text-gray-800">{{ $order->expected_delivery_date ? \Carbon\Carbon::parse($order->expected_delivery_date)->format('d M Y') : '-' }}</dd>
            </div>
            @if($order->notes)
            <div class="col-span-2">
                <dt class="text-gray-500">Notes</dt>
                <dd class="text-gray-700">{{ $order->notes }}</dd>
            </div>
            @endif
        </dl>
    </div>

    <!-- Supplier Info -->
    <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Supplier</h2>
        @if($order->supplier)
        <dl class="space-y-2 text-sm">
            <div>
                <dt class="text-gray-500">Name</dt>
                <dd class="font-semibold text-gray-800">{{ $order->supplier->name }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Contact</dt>
                <dd class="text-gray-700">{{ $order->supplier->contact_person }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Email</dt>
                <dd class="text-gray-700">{{ $order->supplier->email }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Phone</dt>
                <dd class="text-gray-700">{{ $order->supplier->phone }}</dd>
            </div>
        </dl>
        @endif
    </div>
</div>

<!-- Items Table -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-base font-semibold text-gray-700">Order Items</h2>
    </div>
    <table class="table-base">
        <thead>
            <tr>
                <th>Item</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Rate</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($order->items as $item)
            <tr>
                <td class="text-gray-800">{{ $item->item->item_name ?? $item->item_name }}</td>
                <td class="text-right text-gray-600">{{ number_format($item->quantity, 2) }}</td>
                <td class="text-right text-gray-600">{{ number_format($item->rate, 2) }}</td>
                <td class="text-right font-medium text-gray-800">{{ number_format($item->total, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">No items.</td></tr>
            @endforelse
        </tbody>
        <tfoot class="bg-gray-50">
            <tr>
                <td colspan="3" class="px-4 py-3 text-right font-semibold text-gray-700">Total Amount</td>
                <td class="px-4 py-3 text-right font-bold text-gray-900">{{ number_format($order->total_amount, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

<!-- GRNs Linked -->
@if($order->goodsReceiptNotes && $order->goodsReceiptNotes->count())
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-base font-semibold text-gray-700">Goods Receipt Notes</h2>
    </div>
    <table class="table-base">
        <thead>
            <tr>
                <th>GRN #</th>
                <th>Warehouse</th>
                <th>Received Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->goodsReceiptNotes as $grn)
            <tr>
                <td class="font-mono text-gray-700">{{ $grn->grn_number ?? 'GRN-' . str_pad($grn->id, 5, '0', STR_PAD_LEFT) }}</td>
                <td class="text-gray-700">{{ $grn->warehouse->name ?? '' }}</td>
                <td>{{ $grn->received_date ? \Carbon\Carbon::parse($grn->received_date)->format('d M Y') : '' }}</td>
                <td><span class="badge-green">{{ ucfirst($grn->status ?? 'received') }}</span></td>
                <td><a href="{{ route('purchase.grns.show', $grn) }}" class="btn-primary btn-sm">View</a></td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
