@extends('layouts.app')

@section('title', 'Delivery Notes')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Delivery Notes</h1>
        <p class="page-subtitle">Manage goods dispatch to customers</p>
    </div>
    <a href="{{ route('sales.delivery-notes.create') }}" class="btn-primary">
        + New Delivery Note
    </a>
</div>

<div class="table-wrapper overflow-x-auto">
    <table class="table-base">
        <thead>
            <tr>
                <th>DN #</th>
                <th>Sales Order</th>
                <th>Customer</th>
                <th>Warehouse</th>
                <th>Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($deliveryNotes as $dn)
            <tr>
                <td class="font-mono text-gray-700">{{ $dn->dn_number ?? 'DN-' . str_pad($dn->id, 5, '0', STR_PAD_LEFT) }}</td>
                <td class="font-mono text-gray-600">{{ $dn->salesOrder->order_number ?? 'SO-' . str_pad($dn->sales_order_id, 5, '0', STR_PAD_LEFT) }}</td>
                <td class="text-gray-800">{{ $dn->salesOrder->customer->name ?? '' }}</td>
                <td class="text-gray-700">{{ $dn->warehouse->name ?? '' }}</td>
                <td class="text-gray-600">{{ $dn->delivery_date ? \Carbon\Carbon::parse($dn->delivery_date)->format('d M Y') : '' }}</td>
                <td>
                    @php
                        $badgeClass = match($dn->status ?? 'pending') {
                            'pending'    => 'badge-yellow',
                            'dispatched' => 'badge-green',
                            'cancelled'  => 'badge-red',
                            default      => 'badge-gray',
                        };
                    @endphp
                    <span class="{{ $badgeClass }}">{{ ucfirst($dn->status ?? 'pending') }}</span>
                </td>
                <td>
                    <div class="flex items-center gap-2 flex-wrap">
                        @if($dn->status === 'pending')
                        <form action="{{ route('sales.delivery-notes.dispatch', $dn) }}" method="POST">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn-success btn-sm">Dispatch</button>
                        </form>
                        @endif
                        <a href="{{ route('sales.delivery-notes.edit', $dn) }}" class="btn-secondary btn-sm">Edit</a>
                        <form action="{{ route('sales.delivery-notes.destroy', $dn) }}" method="POST"
                              onsubmit="confirmDelete(this,'Delete this delivery note?','This delivery note will be permanently removed.'); return false;">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-400">No delivery notes found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($deliveryNotes->hasPages())
<div class="mt-4">{{ $deliveryNotes->links() }}</div>
@endif
@endsection
