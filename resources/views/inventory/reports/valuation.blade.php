@extends('layouts.app')

@section('title', 'Inventory Valuation')

@section('content')
<div class="mb-6">
    <h1 class="page-title">Inventory Valuation</h1>
    <p class="page-subtitle">Total value of current stock</p>
</div>

<div class="table-wrapper overflow-x-auto">
    <table class="table-base">
        <thead>
            <tr>
                <th>Item Code</th>
                <th>Item Name</th>
                <th>Category</th>
                <th class="text-right">Total Qty</th>
                <th class="text-right">Cost Price</th>
                <th class="text-right">Total Value</th>
            </tr>
        </thead>
        <tbody>
            @forelse($valuations as $row)
            <tr>
                <td class="font-mono text-gray-700">{{ $row->item_code }}</td>
                <td class="font-medium text-gray-800">{{ $row->item_name }}</td>
                <td>
                    @php
                        $catBadgeClass = match($row->category) {
                            'raw_material' => 'badge-blue',
                            'wip' => 'badge-yellow',
                            'finished_good' => 'badge-green',
                            default => 'badge-gray',
                        };
                        $catLabels = ['raw_material'=>'Raw Material','wip'=>'WIP','finished_good'=>'Finished Good'];
                    @endphp
                    <span class="{{ $catBadgeClass }}">
                        {{ $catLabels[$row->category] ?? ucfirst($row->category) }}
                    </span>
                </td>
                <td class="text-right text-gray-700">{{ number_format($row->total_qty, 2) }}</td>
                <td class="text-right text-gray-600">{{ number_format($row->cost_price, 2) }}</td>
                <td class="text-right font-semibold text-gray-800">{{ number_format($row->total_value, 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-400">No valuation data available.</td>
            </tr>
            @endforelse
        </tbody>
        @if(isset($grandTotal))
        <tfoot class="bg-blue-50 border-t-2 border-blue-200">
            <tr>
                <td colspan="5" class="px-4 py-3 text-right font-bold text-gray-800 text-sm">Grand Total</td>
                <td class="px-4 py-3 text-right font-bold text-blue-700 text-base">{{ number_format($grandTotal, 2) }}</td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>
@endsection
