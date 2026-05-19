@extends('layouts.app')

@section('title', 'Edit Item')

@section('content')
<div class="mb-6">
    <h1 class="page-title">Edit Item</h1>
    <p class="page-subtitle"><a href="{{ route('inventory.items.index') }}" class="text-blue-600 hover:underline">Items</a> / Edit</p>
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

<div class="card card-body max-w-2xl">
    <form action="{{ route('inventory.items.update', $item) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

            <div>
                <label class="form-label">Item Code <span class="text-red-500">*</span></label>
                <input type="text" name="item_code" value="{{ old('item_code', $item->item_code) }}" required class="form-input">
            </div>

            <div>
                <label class="form-label">Item Name <span class="text-red-500">*</span></label>
                <input type="text" name="item_name" value="{{ old('item_name', $item->item_name) }}" required class="form-input">
            </div>

            <div>
                <label class="form-label">Category <span class="text-red-500">*</span></label>
                <select name="category" required class="form-select">
                    <option value="">-- Select Category --</option>
                    <option value="raw_material" {{ old('category', $item->category) === 'raw_material' ? 'selected' : '' }}>Raw Material</option>
                    <option value="wip" {{ old('category', $item->category) === 'wip' ? 'selected' : '' }}>Work In Progress (WIP)</option>
                    <option value="finished_good" {{ old('category', $item->category) === 'finished_good' ? 'selected' : '' }}>Finished Good</option>
                </select>
            </div>

            <div>
                <label class="form-label">Unit of Measure <span class="text-red-500">*</span></label>
                <input type="text" name="unit_of_measure" value="{{ old('unit_of_measure', $item->unit_of_measure) }}" required class="form-input">
            </div>

            <div>
                <label class="form-label">Minimum Stock Level</label>
                <input type="number" name="minimum_stock_level" value="{{ old('minimum_stock_level', $item->minimum_stock_level) }}" min="0" step="0.01" class="form-input">
            </div>

            <div>
                <label class="form-label">Cost Price</label>
                <input type="number" name="cost_price" value="{{ old('cost_price', $item->cost_price) }}" min="0" step="0.01" class="form-input">
            </div>

            <div class="sm:col-span-2">
                <label class="form-label">Description</label>
                <textarea name="description" rows="3" class="form-textarea">{{ old('description', $item->description) }}</textarea>
            </div>

            <div class="sm:col-span-2 flex items-center gap-2">
                <input type="checkbox" name="is_active" id="is_active" value="1"
                       {{ old('is_active', $item->is_active) ? 'checked' : '' }}
                       class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                <label for="is_active" class="form-label mb-0">Active</label>
            </div>

        </div>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="btn-primary">Update Item</button>
            <a href="{{ route('inventory.items.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
