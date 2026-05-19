@extends('layouts.app')

@section('title', 'Inventory Summary')

@section('content')
<div class="mb-6">
    <h1 class="page-title">Inventory Summary</h1>
    <p class="page-subtitle">Current stock levels across all warehouses</p>
</div>

<div class="table-wrapper overflow-x-auto">
    <table class="table-base">
        <thead>
            <tr>
                <th>Item Code</th>
                <th>Item Name</th>
                <th>Warehouse</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Min Stock</th>
            </tr>
        </thead>
        <tbody>
            @forelse($stocks as $stock)
            @php $isLow = $stock->quantity < $stock->item->minimum_stock_level; @endphp
            <tr class="{{ $isLow ? 'bg-red-50' : '' }}">
                <td class="font-mono {{ $isLow ? 'text-red-700' : 'text-gray-700' }}">{{ $stock->item->item_code ?? '' }}</td>
                <td class="font-medium {{ $isLow ? 'text-red-800' : 'text-gray-800' }}">
                    {{ $stock->item->item_name ?? '' }}
                    @if($isLow)
                        <span class="ml-2 px-1.5 py-0.5 text-xs font-bold rounded bg-red-200 text-red-800">LOW</span>
                    @endif
                </td>
                <td>{{ $stock->warehouse->name ?? '' }}</td>
                <td class="text-right font-semibold {{ $isLow ? 'text-red-700' : 'text-gray-800' }}">{{ number_format($stock->quantity, 2) }}</td>
                <td class="text-right text-gray-500">{{ number_format($stock->item->minimum_stock_level ?? 0, 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-4 py-8 text-center text-gray-400">No stock data available.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
