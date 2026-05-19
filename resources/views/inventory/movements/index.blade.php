@extends('layouts.app')

@section('title', 'Stock Movements')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Stock Movements</h1>
        <p class="page-subtitle">Track all inventory movements</p>
    </div>
    <a href="{{ route('inventory.movements.create') }}" class="btn-primary">
        + Manual Adjustment
    </a>
</div>

<div class="table-wrapper overflow-x-auto">
    <table class="table-base">
        <thead>
            <tr>
                <th>Item</th>
                <th>Warehouse</th>
                <th>Type</th>
                <th class="text-right">Quantity</th>
                <th>Reference</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($movements as $movement)
            <tr>
                <td class="text-gray-800">
                    <span class="font-mono text-xs text-gray-500">{{ $movement->item->item_code ?? '' }}</span>
                    <span class="ml-1">{{ $movement->item->item_name ?? '' }}</span>
                </td>
                <td class="text-gray-700">{{ $movement->warehouse->name ?? '' }}</td>
                <td>
                    @if(strtolower($movement->type) === 'in')
                        <span class="badge-green">IN</span>
                    @else
                        <span class="badge-red">OUT</span>
                    @endif
                </td>
                <td class="text-right font-medium text-gray-800">{{ number_format($movement->quantity, 2) }}</td>
                <td class="text-gray-500 text-xs">{{ $movement->reference ?? '-' }}</td>
                <td>{{ $movement->created_at ? $movement->created_at->format('d M Y') : '' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-400">No movements recorded.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($movements->hasPages())
<div class="mt-4">{{ $movements->links() }}</div>
@endif
@endsection
