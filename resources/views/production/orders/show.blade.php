@extends('layouts.app')

@section('title', 'Production Order')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Production Order</h1>
        <p class="page-subtitle">
            <a href="{{ route('production.orders.index') }}" class="text-blue-600 hover:underline">Production Orders</a>
            / {{ $order->order_number ?? 'PRD-' . str_pad($order->id, 5, '0', STR_PAD_LEFT) }}
        </p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('production.orders.edit', $order) }}" class="btn-secondary">Edit</a>
        @if($order->status === 'pending')
        <form action="{{ route('production.orders.start', $order) }}" method="POST">
            @csrf @method('PATCH')
            <button type="submit" class="btn-primary">Start Production</button>
        </form>
        @endif
        @if($order->status === 'in_progress')
        <form action="{{ route('production.orders.complete', $order) }}" method="POST">
            @csrf @method('PATCH')
            <button type="submit" class="btn-success">Mark Complete</button>
        </form>
        @endif
    </div>
</div>

<!-- Order Details -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Order Details</h2>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between">
                <dt class="text-gray-500">Order Number</dt>
                <dd class="font-mono font-semibold text-gray-800">{{ $order->order_number ?? 'PRD-' . str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Product</dt>
                <dd class="font-medium text-gray-800">{{ $order->product->item_name ?? '' }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Qty to Produce</dt>
                <dd class="text-gray-800">{{ number_format($order->quantity_to_produce, 2) }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Qty Produced</dt>
                <dd class="text-gray-800">{{ number_format($order->quantity_produced ?? 0, 2) }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Production Date</dt>
                <dd class="text-gray-800">{{ $order->production_date ? \Carbon\Carbon::parse($order->production_date)->format('d M Y') : '-' }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Status</dt>
                <dd>
                    @php
                        $badgeClass = match($order->status ?? 'pending') {
                            'pending' => 'badge-yellow',
                            'in_progress' => 'badge-blue',
                            'completed' => 'badge-green',
                            default => 'badge-gray',
                        };
                    @endphp
                    <span class="{{ $badgeClass }}">{{ ucwords(str_replace('_', ' ', $order->status ?? 'pending')) }}</span>
                </dd>
            </div>
            @if($order->notes)
            <div>
                <dt class="text-gray-500">Notes</dt>
                <dd class="text-gray-700 mt-1">{{ $order->notes }}</dd>
            </div>
            @endif
        </dl>
    </div>

    <!-- BOM for product -->
    <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Bill of Materials</h2>
        @if(isset($bom) && $bom->count())
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="pb-2 text-left font-semibold text-gray-600">Material</th>
                    <th class="pb-2 text-right font-semibold text-gray-600">Qty Required</th>
                    <th class="pb-2 text-left font-semibold text-gray-600">UOM</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($bom as $bomItem)
                <tr>
                    <td class="py-2 text-gray-800">{{ $bomItem->rawMaterial->item_name ?? '' }}</td>
                    <td class="py-2 text-right text-gray-700">{{ number_format($bomItem->quantity_required, 2) }}</td>
                    <td class="py-2 text-gray-500 pl-2">{{ $bomItem->unit_of_measure }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p class="text-sm text-gray-400">No BOM defined for this product.</p>
        @endif
    </div>
</div>

<!-- Material Issues -->
@if(isset($materialIssues) && $materialIssues->count())
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
        <h2 class="text-base font-semibold text-gray-700">Material Issues</h2>
        <a href="{{ route('production.material-issues.create', ['production_order_id' => $order->id]) }}"
           class="btn-primary btn-sm">+ Issue Material</a>
    </div>
    <table class="table-base">
        <thead>
            <tr>
                <th>Item</th>
                <th>Warehouse</th>
                <th class="text-right">Qty</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($materialIssues as $issue)
            <tr>
                <td class="text-gray-800">{{ $issue->item->item_name ?? '' }}</td>
                <td>{{ $issue->warehouse->name ?? '' }}</td>
                <td class="text-right text-gray-700">{{ number_format($issue->quantity, 2) }}</td>
                <td>{{ $issue->issue_date ? \Carbon\Carbon::parse($issue->issue_date)->format('d M Y') : '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<!-- Production Output -->
@if(isset($outputs) && $outputs->count())
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
        <h2 class="text-base font-semibold text-gray-700">Production Output</h2>
        <a href="{{ route('production.outputs.create', ['production_order_id' => $order->id]) }}"
           class="btn-primary btn-sm">+ Record Output</a>
    </div>
    <table class="table-base">
        <thead>
            <tr>
                <th>Item</th>
                <th>Warehouse</th>
                <th class="text-right">Qty</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($outputs as $output)
            <tr>
                <td class="text-gray-800">{{ $output->item->item_name ?? '' }}</td>
                <td>{{ $output->warehouse->name ?? '' }}</td>
                <td class="text-right text-gray-700">{{ number_format($output->quantity, 2) }}</td>
                <td>{{ $output->output_date ? \Carbon\Carbon::parse($output->output_date)->format('d M Y') : '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
