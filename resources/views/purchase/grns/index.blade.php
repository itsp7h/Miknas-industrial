@extends('layouts.app')

@section('title', 'Goods Receipt Notes')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Goods Receipt Notes</h1>
        <p class="page-subtitle">Record goods received from suppliers</p>
    </div>
    <a href="{{ route('purchase.grns.create') }}" class="btn-primary">
        + New GRN
    </a>
</div>

<div class="table-wrapper overflow-x-auto">
    <table class="table-base">
        <thead>
            <tr>
                <th>GRN #</th>
                <th>PO #</th>
                <th>Supplier</th>
                <th>Warehouse</th>
                <th>Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($grns as $grn)
            <tr>
                <td class="font-mono text-gray-700">{{ $grn->grn_number ?? 'GRN-' . str_pad($grn->id, 5, '0', STR_PAD_LEFT) }}</td>
                <td class="font-mono text-gray-600">{{ $grn->purchaseOrder->po_number ?? 'PO-' . str_pad($grn->purchase_order_id, 5, '0', STR_PAD_LEFT) }}</td>
                <td class="text-gray-800">{{ $grn->purchaseOrder->supplier->name ?? '' }}</td>
                <td class="text-gray-700">{{ $grn->warehouse->name ?? '' }}</td>
                <td>{{ $grn->received_date ? \Carbon\Carbon::parse($grn->received_date)->format('d M Y') : '' }}</td>
                <td>
                    <span class="badge-green">{{ ucfirst($grn->status ?? 'received') }}</span>
                </td>
                <td>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('purchase.grns.show', $grn) }}" class="btn-primary btn-sm">View</a>
                        <form action="{{ route('purchase.grns.destroy', $grn) }}" method="POST"
                              onsubmit="confirmDelete(this,'Delete this GRN?','This goods receipt note will be permanently removed.'); return false;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-400">No GRNs found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($grns->hasPages())
<div class="mt-4">{{ $grns->links() }}</div>
@endif
@endsection
