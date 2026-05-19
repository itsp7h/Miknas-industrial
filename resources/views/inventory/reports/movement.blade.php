@extends('layouts.app')

@section('title', 'Movement Report')

@section('content')
<div class="mb-6">
    <h1 class="page-title">Movement Report</h1>
    <p class="page-subtitle">View stock movements within a date range</p>
</div>

<!-- Date Range Filter -->
<div class="card card-body mb-6">
    <form method="GET" action="{{ route('inventory.reports.movement') }}" class="flex flex-wrap items-end gap-4">
        <div>
            <label class="form-label">From Date</label>
            <input type="date" name="from_date" value="{{ request('from_date') }}" class="form-input">
        </div>
        <div>
            <label class="form-label">To Date</label>
            <input type="date" name="to_date" value="{{ request('to_date') }}" class="form-input">
        </div>
        <div>
            <label class="form-label">Item (optional)</label>
            <select name="item_id" class="form-select">
                <option value="">All Items</option>
                @foreach($items as $item)
                    <option value="{{ $item->id }}" {{ request('item_id') == $item->id ? 'selected' : '' }}>
                        {{ $item->item_code }} - {{ $item->item_name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <button type="submit" class="btn-primary">Filter</button>
        </div>
    </form>
</div>

<div class="table-wrapper overflow-x-auto">
    <table class="table-base">
        <thead>
            <tr>
                <th>Item</th>
                <th>Warehouse</th>
                <th>Type</th>
                <th class="text-right">Quantity</th>
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
                <td>{{ $movement->created_at ? $movement->created_at->format('d M Y') : '' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-4 py-8 text-center text-gray-400">No movements found for selected filters.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(isset($movements) && $movements->hasPages())
<div class="mt-4">{{ $movements->links() }}</div>
@endif
@endsection
