@extends('layouts.app')

@section('title', 'Edit Purchase Order')

@section('content')
<div class="mb-6">
    <h1 class="page-title">Edit Purchase Order</h1>
    <p class="page-subtitle"><a href="{{ route('purchase.orders.index') }}" class="text-blue-600 hover:underline">Purchase Orders</a> / Edit</p>
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
    <form action="{{ route('purchase.orders.update', $order) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

            <div>
                <label class="form-label">Supplier <span class="text-red-500">*</span></label>
                <select name="supplier_id" required class="form-select">
                    <option value="">-- Select Supplier --</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ old('supplier_id', $order->supplier_id) == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">PO Date <span class="text-red-500">*</span></label>
                <input type="date" name="po_date" value="{{ old('po_date', $order->po_date ? \Carbon\Carbon::parse($order->po_date)->format('Y-m-d') : '') }}" required class="form-input">
            </div>

            <div>
                <label class="form-label">Expected Delivery Date</label>
                <input type="date" name="expected_delivery_date" value="{{ old('expected_delivery_date', $order->expected_delivery_date ? \Carbon\Carbon::parse($order->expected_delivery_date)->format('Y-m-d') : '') }}" class="form-input">
            </div>

            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="draft" {{ old('status', $order->status) === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="sent" {{ old('status', $order->status) === 'sent' ? 'selected' : '' }}>Sent</option>
                    <option value="received" {{ old('status', $order->status) === 'received' ? 'selected' : '' }}>Received</option>
                    <option value="cancelled" {{ old('status', $order->status) === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>

            <div class="sm:col-span-2">
                <label class="form-label">Notes</label>
                <textarea name="notes" rows="3" class="form-textarea">{{ old('notes', $order->notes) }}</textarea>
            </div>

        </div>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="btn-primary">Update Order</button>
            <a href="{{ route('purchase.orders.show', $order) }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
