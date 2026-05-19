@extends('layouts.app')

@section('title', 'Edit Production Order')

@section('content')
<div class="mb-6">
    <h1 class="page-title">Edit Production Order</h1>
    <p class="page-subtitle"><a href="{{ route('production.orders.index') }}" class="text-blue-600 hover:underline">Production Orders</a> / Edit</p>
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
    <form action="{{ route('production.orders.update', $order) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

            <div class="sm:col-span-2">
                <label class="form-label">Product (Finished Good) <span class="text-red-500">*</span></label>
                <select name="product_id" required class="form-select">
                    <option value="">-- Select Product --</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" {{ old('product_id', $order->product_id) == $product->id ? 'selected' : '' }}>
                            {{ $product->item_code }} - {{ $product->item_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Quantity to Produce <span class="text-red-500">*</span></label>
                <input type="number" name="quantity_to_produce" value="{{ old('quantity_to_produce', $order->quantity_to_produce) }}" min="0.01" step="0.01" required class="form-input">
            </div>

            <div>
                <label class="form-label">Production Date <span class="text-red-500">*</span></label>
                <input type="date" name="production_date" value="{{ old('production_date', $order->production_date ? \Carbon\Carbon::parse($order->production_date)->format('Y-m-d') : '') }}" required class="form-input">
            </div>

            <div class="sm:col-span-2">
                <label class="form-label">Notes</label>
                <textarea name="notes" rows="3" class="form-textarea">{{ old('notes', $order->notes) }}</textarea>
            </div>

        </div>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="btn-primary">Update Order</button>
            <a href="{{ route('production.orders.show', $order) }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
