@extends('layouts.app')

@section('title', 'Goods Receipt Note')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Goods Receipt Note</h1>
        <p class="page-subtitle">
            <a href="{{ route('purchase.grns.index') }}" class="text-blue-600 hover:underline">GRNs</a>
            / {{ $grn->grn_number ?? 'GRN-' . str_pad($grn->id, 5, '0', STR_PAD_LEFT) }}
        </p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">GRN Details</h2>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between">
                <dt class="text-gray-500">GRN Number</dt>
                <dd class="font-mono font-semibold text-gray-800">{{ $grn->grn_number ?? 'GRN-' . str_pad($grn->id, 5, '0', STR_PAD_LEFT) }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Purchase Order</dt>
                <dd class="font-mono text-gray-800">
                    <a href="{{ route('purchase.orders.show', $grn->purchaseOrder) }}" class="text-blue-600 hover:underline">
                        {{ $grn->purchaseOrder->po_number ?? 'PO-' . str_pad($grn->purchase_order_id, 5, '0', STR_PAD_LEFT) }}
                    </a>
                </dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Supplier</dt>
                <dd class="font-medium text-gray-800">{{ $grn->purchaseOrder->supplier->name ?? '-' }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Warehouse</dt>
                <dd class="text-gray-800">{{ $grn->warehouse->name ?? '-' }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Received Date</dt>
                <dd class="text-gray-800">{{ $grn->received_date ? \Carbon\Carbon::parse($grn->received_date)->format('d M Y') : '-' }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Status</dt>
                <dd><span class="badge-green">{{ ucfirst($grn->status ?? 'received') }}</span></dd>
            </div>
            @if($grn->notes)
            <div>
                <dt class="text-gray-500">Notes</dt>
                <dd class="text-gray-700 mt-1">{{ $grn->notes }}</dd>
            </div>
            @endif
        </dl>
    </div>
</div>

<!-- Items Received -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-base font-semibold text-gray-700">Items Received</h2>
    </div>
    <table class="table-base">
        <thead>
            <tr>
                <th>Item</th>
                <th class="text-right">PO Qty</th>
                <th class="text-right">Qty Received</th>
            </tr>
        </thead>
        <tbody>
            @forelse($grn->items as $item)
            <tr>
                <td class="text-gray-800">{{ $item->item->item_name ?? '' }}</td>
                <td class="text-right text-gray-600">{{ number_format($item->quantity_ordered ?? 0, 2) }}</td>
                <td class="text-right font-medium text-gray-800">{{ number_format($item->quantity_received, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">No items recorded.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
