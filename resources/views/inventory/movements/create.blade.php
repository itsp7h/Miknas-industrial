@extends('layouts.app')

@section('title', 'Manual Stock Adjustment')

@section('content')
<div class="mb-6">
    <h1 class="page-title">Manual Stock Adjustment</h1>
    <p class="page-subtitle"><a href="{{ route('inventory.movements.index') }}" class="text-blue-600 hover:underline">Stock Movements</a> / New</p>
</div>

@if($errors->any())
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
    <ul class="list-disc list-inside space-y-1">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="card card-body max-w-xl">
    <form action="{{ route('inventory.movements.store') }}" method="POST">
        @csrf
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

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
                <label class="form-label">Type <span class="text-red-500">*</span></label>
                <select name="type" required class="form-select">
                    <option value="">-- Select Type --</option>
                    <option value="in" {{ old('type') === 'in' ? 'selected' : '' }}>IN (Stock In)</option>
                    <option value="out" {{ old('type') === 'out' ? 'selected' : '' }}>OUT (Stock Out)</option>
                </select>
            </div>

            <div>
                <label class="form-label">Quantity <span class="text-red-500">*</span></label>
                <input type="number" name="quantity" value="{{ old('quantity') }}" min="0.01" step="0.01" required class="form-input">
            </div>

            <div class="sm:col-span-2">
                <label class="form-label">Notes</label>
                <textarea name="notes" rows="2" class="form-textarea">{{ old('notes') }}</textarea>
            </div>

        </div>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="btn-primary">Save Adjustment</button>
            <a href="{{ route('inventory.movements.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
