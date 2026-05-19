@extends('layouts.app')

@section('title', 'Edit Delivery Note')

@section('content')
<div class="mb-6">
    <h1 class="page-title">Edit Delivery Note</h1>
    <p class="page-subtitle"><a href="{{ route('sales.delivery-notes.index') }}" class="text-blue-600 hover:underline">Delivery Notes</a> / Edit</p>
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
    <form action="{{ route('sales.delivery-notes.update', $deliveryNote) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

            <div>
                <label class="form-label">Warehouse <span class="text-red-500">*</span></label>
                <select name="warehouse_id" required class="form-select">
                    <option value="">-- Select Warehouse --</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" {{ old('warehouse_id', $deliveryNote->warehouse_id) == $warehouse->id ? 'selected' : '' }}>
                            {{ $warehouse->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Delivery Date <span class="text-red-500">*</span></label>
                <input type="date" name="delivery_date" value="{{ old('delivery_date', $deliveryNote->delivery_date ? \Carbon\Carbon::parse($deliveryNote->delivery_date)->format('Y-m-d') : '') }}" required class="form-input">
            </div>

            <div class="sm:col-span-2">
                <label class="form-label">Notes</label>
                <textarea name="notes" rows="3" class="form-textarea">{{ old('notes', $deliveryNote->notes) }}</textarea>
            </div>

        </div>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="btn-primary">Update Delivery Note</button>
            <a href="{{ route('sales.delivery-notes.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
