@extends('layouts.app')

@section('title', 'Sales Order')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Sales Order</h1>
        <p class="page-subtitle">
            <a href="{{ route('sales.orders.index') }}" class="text-blue-600 hover:underline">Sales Orders</a>
            / {{ $order->order_number ?? 'SO-' . str_pad($order->id, 5, '0', STR_PAD_LEFT) }}
        </p>
    </div>
    <div class="flex gap-2">
        @if($order->status === 'draft')
        <form action="{{ route('sales.orders.confirm', $order) }}" method="POST">
            @csrf @method('PATCH')
            <button type="submit" class="btn-primary">Confirm Order</button>
        </form>
        @endif
        @if($order->status === 'confirmed')
        <a href="{{ route('sales.delivery-notes.create', ['sales_order_id' => $order->id]) }}" class="btn-primary">Create Delivery Note</a>
        @endif
        <a href="{{ route('sales.orders.edit', $order) }}" class="btn-secondary">Edit</a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <!-- Order Info -->
    <div class="lg:col-span-2 card card-body">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Order Details</h2>
        <dl class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">Order Number</dt>
                <dd class="font-mono font-semibold text-gray-800">{{ $order->order_number ?? 'SO-' . str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Status</dt>
                <dd>
                    @php
                        $badgeClass = match($order->status ?? 'draft') {
                            'draft'     => 'badge-gray',
                            'confirmed' => 'badge-blue',
                            'dispatched'=> 'badge-violet',
                            'invoiced'  => 'badge-green',
                            default     => 'badge-gray',
                        };
                    @endphp
                    <span class="{{ $badgeClass }}">{{ ucfirst($order->status ?? 'draft') }}</span>
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Order Date</dt>
                <dd class="font-medium text-gray-800">{{ $order->order_date ? \Carbon\Carbon::parse($order->order_date)->format('d M Y') : '-' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Delivery Date</dt>
                <dd class="font-medium text-gray-800">{{ $order->delivery_date ? \Carbon\Carbon::parse($order->delivery_date)->format('d M Y') : '-' }}</dd>
            </div>
        </dl>
    </div>

    <!-- Customer Info -->
    <div class="card card-body">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Customer</h2>
        @if($order->customer)
        <dl class="space-y-2 text-sm">
            <div><dt class="text-gray-500">Name</dt><dd class="font-semibold text-gray-800">{{ $order->customer->name }}</dd></div>
            <div><dt class="text-gray-500">Contact</dt><dd class="text-gray-700">{{ $order->customer->contact_person }}</dd></div>
            <div><dt class="text-gray-500">Email</dt><dd class="text-gray-700">{{ $order->customer->email }}</dd></div>
            <div><dt class="text-gray-500">Phone</dt><dd class="text-gray-700">{{ $order->customer->phone }}</dd></div>
        </dl>
        @endif
    </div>
</div>

<!-- Items -->
<div class="card mb-6 overflow-hidden">
    <div class="card-header">
        <h2 class="text-base font-semibold text-gray-700">Order Items</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="table-base">
            <thead>
                <tr>
                    <th>Product</th>
                    <th class="text-right">Quantity</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($order->items as $item)
                <tr>
                    <td class="text-gray-800">{{ $item->item->item_name ?? '' }}</td>
                    <td class="text-right text-gray-600">{{ number_format($item->quantity, 2) }}</td>
                    <td class="text-right text-gray-600">{{ number_format($item->price, 2) }}</td>
                    <td class="text-right font-medium text-gray-800">{{ number_format($item->total, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">No items.</td></tr>
                @endforelse
            </tbody>
            <tfoot class="bg-gray-50">
                <tr>
                    <td colspan="3" class="px-4 py-3 text-right font-semibold text-gray-700">Total</td>
                    <td class="px-4 py-3 text-right font-bold text-gray-900">{{ number_format($order->total_amount, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Delivery Notes -->
@if(isset($deliveryNotes) && $deliveryNotes->count())
<div class="card mb-6 overflow-hidden">
    <div class="card-header">
        <h2 class="text-base font-semibold text-gray-700">Delivery Notes</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="table-base">
            <thead>
                <tr>
                    <th>DN #</th>
                    <th>Warehouse</th>
                    <th>Delivery Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($deliveryNotes as $dn)
                <tr>
                    <td class="font-mono text-gray-700">{{ $dn->dn_number ?? 'DN-' . str_pad($dn->id, 5, '0', STR_PAD_LEFT) }}</td>
                    <td class="text-gray-700">{{ $dn->warehouse->name ?? '' }}</td>
                    <td class="text-gray-600">{{ $dn->delivery_date ? \Carbon\Carbon::parse($dn->delivery_date)->format('d M Y') : '' }}</td>
                    <td><span class="badge-blue">{{ ucfirst($dn->status ?? 'pending') }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<!-- Invoices -->
@if(isset($invoices) && $invoices->count())
<div class="card overflow-hidden">
    <div class="card-header">
        <h2 class="text-base font-semibold text-gray-700">Invoices</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="table-base">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date</th>
                    <th class="text-right">Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoices as $invoice)
                <tr>
                    <td class="font-mono text-gray-700">{{ $invoice->invoice_number }}</td>
                    <td class="text-gray-600">{{ $invoice->invoice_date ? \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') : '' }}</td>
                    <td class="text-right font-medium text-gray-800">{{ number_format($invoice->total_amount, 2) }}</td>
                    <td><span class="badge-green">{{ ucfirst($invoice->status ?? 'unpaid') }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
