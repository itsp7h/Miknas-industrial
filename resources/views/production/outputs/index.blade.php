@extends('layouts.app')

@section('title', 'Production Output')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Production Output</h1>
        <p class="page-subtitle">Record finished goods produced</p>
    </div>
    <a href="{{ route('production.outputs.create') }}" class="btn-primary">
        + Record Output
    </a>
</div>

<div class="table-wrapper overflow-x-auto">
    <table class="table-base">
        <thead>
            <tr>
                <th>Production Order</th>
                <th>Item</th>
                <th>Warehouse</th>
                <th class="text-right">Quantity</th>
                <th>Output Date</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($outputs as $output)
            <tr>
                <td class="font-mono text-gray-700">
                    <a href="{{ route('production.orders.show', $output->productionOrder) }}" class="text-blue-600 hover:underline">
                        {{ $output->productionOrder->order_number ?? 'PRD-' . str_pad($output->production_order_id, 5, '0', STR_PAD_LEFT) }}
                    </a>
                </td>
                <td class="text-gray-800">{{ $output->item->item_name ?? '' }}</td>
                <td>{{ $output->warehouse->name ?? '' }}</td>
                <td class="text-right font-medium text-gray-800">{{ number_format($output->quantity, 2) }}</td>
                <td>{{ $output->output_date ? \Carbon\Carbon::parse($output->output_date)->format('d M Y') : '' }}</td>
                <td class="text-gray-500 text-xs">{{ $output->notes ?? '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-400">No production outputs recorded.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($outputs->hasPages())
<div class="mt-4">{{ $outputs->links() }}</div>
@endif

<!-- Inline Create Form -->
<div class="mt-8">
    <h2 class="text-lg font-bold text-gray-800 mb-4">Record Production Output</h2>

    @if($errors->any())
    <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="card card-body max-w-2xl">
        <form action="{{ route('production.outputs.store') }}" method="POST">
            @csrf
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

                <div>
                    <label class="form-label">Production Order <span class="text-red-500">*</span></label>
                    <select name="production_order_id" required class="form-select">
                        <option value="">-- Select Order --</option>
                        @foreach($productionOrders as $po)
                            <option value="{{ $po->id }}" {{ (old('production_order_id', request('production_order_id'))) == $po->id ? 'selected' : '' }}>
                                {{ $po->order_number ?? 'PRD-' . str_pad($po->id, 5, '0', STR_PAD_LEFT) }} - {{ $po->product->item_name ?? '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label">Item (Finished Good) <span class="text-red-500">*</span></label>
                    <select name="item_id" required class="form-select">
                        <option value="">-- Select Item --</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" {{ old('item_id') == $product->id ? 'selected' : '' }}>
                                {{ $product->item_code }} - {{ $product->item_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label">Warehouse <span class="text-red-500">*</span></label>
                    <select name="warehouse_id" required class="form-select">
                        <option value="">-- Select Warehouse --</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" {{ old('warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                                {{ $warehouse->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label">Quantity <span class="text-red-500">*</span></label>
                    <input type="number" name="quantity" value="{{ old('quantity') }}" min="0.001" step="0.001" required class="form-input">
                </div>

                <div>
                    <label class="form-label">Output Date <span class="text-red-500">*</span></label>
                    <input type="date" name="output_date" value="{{ old('output_date', date('Y-m-d')) }}" required class="form-input">
                </div>

                <div>
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" class="form-input">
                </div>

            </div>

            <div class="mt-6">
                <button type="submit" class="btn-primary">Record Output</button>
            </div>
        </form>
    </div>
</div>
@endsection
