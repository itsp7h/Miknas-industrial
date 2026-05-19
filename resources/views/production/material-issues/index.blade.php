@extends('layouts.app')

@section('title', 'Material Issues')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Material Issues</h1>
        <p class="page-subtitle">Record materials issued for production</p>
    </div>
    <a href="{{ route('production.material-issues.create') }}" class="btn-primary">
        + Issue Material
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
                <th>Issue Date</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($materialIssues as $issue)
            <tr>
                <td class="font-mono text-gray-700">
                    <a href="{{ route('production.orders.show', $issue->productionOrder) }}" class="text-blue-600 hover:underline">
                        {{ $issue->productionOrder->order_number ?? 'PRD-' . str_pad($issue->production_order_id, 5, '0', STR_PAD_LEFT) }}
                    </a>
                </td>
                <td class="text-gray-800">{{ $issue->item->item_name ?? '' }}</td>
                <td>{{ $issue->warehouse->name ?? '' }}</td>
                <td class="text-right text-gray-700">{{ number_format($issue->quantity, 2) }}</td>
                <td>{{ $issue->issue_date ? \Carbon\Carbon::parse($issue->issue_date)->format('d M Y') : '' }}</td>
                <td class="text-gray-500 text-xs">{{ $issue->notes ?? '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-400">No material issues found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($materialIssues->hasPages())
<div class="mt-4">{{ $materialIssues->links() }}</div>
@endif

<!-- Inline Create Form -->
<div class="mt-8">
    <h2 class="text-lg font-bold text-gray-800 mb-4">Issue New Material</h2>

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
        <form action="{{ route('production.material-issues.store') }}" method="POST">
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
                    <label class="form-label">Item <span class="text-red-500">*</span></label>
                    <select name="item_id" required class="form-select">
                        <option value="">-- Select Item --</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}" {{ old('item_id') == $item->id ? 'selected' : '' }}>
                                {{ $item->item_code }} - {{ $item->item_name }}
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
                    <label class="form-label">Issue Date <span class="text-red-500">*</span></label>
                    <input type="date" name="issue_date" value="{{ old('issue_date', date('Y-m-d')) }}" required class="form-input">
                </div>

                <div>
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" class="form-input">
                </div>

            </div>

            <div class="mt-6">
                <button type="submit" class="btn-primary">Issue Material</button>
            </div>
        </form>
    </div>
</div>
@endsection
