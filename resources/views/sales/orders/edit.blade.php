@extends('layouts.app')

@section('title', 'Edit Sales Order')

@section('content')
<div class="mb-6">
    <h1 class="page-title">Edit Sales Order</h1>
    <p class="page-subtitle"><a href="{{ route('sales.orders.index') }}" class="text-blue-600 hover:underline">Sales Orders</a> / Edit</p>
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
    <form action="{{ route('sales.orders.update', $salesOrder) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

            <div>
                <label class="form-label">Customer <span class="text-red-500">*</span></label>
                <select name="customer_id" required class="form-select">
                    <option value="">-- Select Customer --</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" {{ old('customer_id', $salesOrder->customer_id) == $customer->id ? 'selected' : '' }}>
                            {{ $customer->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Order Date <span class="text-red-500">*</span></label>
                <input type="date" name="order_date" value="{{ old('order_date', $salesOrder->order_date ? \Carbon\Carbon::parse($salesOrder->order_date)->format('Y-m-d') : '') }}" required class="form-input">
            </div>

            <div>
                <label class="form-label">Delivery Date</label>
                <input type="date" name="delivery_date" value="{{ old('delivery_date', $salesOrder->delivery_date ? \Carbon\Carbon::parse($salesOrder->delivery_date)->format('Y-m-d') : '') }}" class="form-input">
            </div>

            <div class="sm:col-span-2">
                <label class="form-label">Notes</label>
                <textarea name="notes" rows="3" class="form-textarea">{{ old('notes', $salesOrder->notes) }}</textarea>
            </div>

        </div>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="btn-primary">Update Order</button>
            <a href="{{ route('sales.orders.show', $salesOrder) }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
