@extends('layouts.app')

@section('title', 'Low Stock Report')

@section('content')
<div class="mb-6">
    <h1 class="page-title">Low Stock Report</h1>
    <p class="page-subtitle">Items currently below their minimum stock level</p>
</div>

@if(isset($stocks) && $stocks->count() > 0)
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 font-medium">
    {{ $stocks->count() }} item(s) are below minimum stock level. Immediate action may be required.
</div>
@endif

<div class="table-wrapper overflow-x-auto">
    <table class="table-base">
        <thead>
            <tr>
                <th>Item Code</th>
                <th>Item Name</th>
                <th>Category</th>
                <th>Warehouse</th>
                <th class="text-right">Current Qty</th>
                <th class="text-right">Min Stock</th>
                <th class="text-right">Shortage</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($stocks as $stock)
            @php $shortage = $stock->item->minimum_stock_level - $stock->quantity; @endphp
            <tr class="bg-red-50">
                <td class="font-mono text-red-700">{{ $stock->item->item_code ?? '' }}</td>
                <td class="font-medium text-red-800">{{ $stock->item->item_name ?? '' }}</td>
                <td>
                    @php
                        $catLabels = ['raw_material'=>'Raw Material','wip'=>'WIP','finished_good'=>'Finished Good'];
                    @endphp
                    <span class="text-gray-600">{{ $catLabels[$stock->item->category ?? ''] ?? ucfirst($stock->item->category ?? '') }}</span>
                </td>
                <td class="text-gray-600">{{ $stock->warehouse->name ?? '' }}</td>
                <td class="text-right font-bold text-red-700">{{ number_format($stock->quantity, 2) }}</td>
                <td class="text-right text-gray-600">{{ number_format($stock->item->minimum_stock_level ?? 0, 2) }}</td>
                <td class="text-right font-semibold text-red-600">{{ number_format($shortage, 2) }}</td>
                <td>
                    <span class="badge-red">LOW STOCK</span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="px-4 py-8 text-center text-green-600 font-medium">
                    All items are above minimum stock levels.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
