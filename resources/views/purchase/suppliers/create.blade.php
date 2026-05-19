@extends('layouts.app')

@section('title', 'Add Supplier')

@section('content')
<div class="mb-6">
    <h1 class="page-title">Add Supplier</h1>
    <p class="page-subtitle"><a href="{{ route('purchase.suppliers.index') }}" class="text-blue-600 hover:underline">Suppliers</a> / New</p>
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
    <form action="{{ route('purchase.suppliers.store') }}" method="POST">
        @csrf
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

            <div class="sm:col-span-2">
                <label class="form-label">Supplier Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required class="form-input">
            </div>

            <div>
                <label class="form-label">Contact Person</label>
                <input type="text" name="contact_person" value="{{ old('contact_person') }}" class="form-input">
            </div>

            <div>
                <label class="form-label">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" class="form-input">
            </div>

            <div>
                <label class="form-label">Phone</label>
                <input type="text" name="phone" value="{{ old('phone') }}" class="form-input">
            </div>

            <div>
                <label class="form-label">Tax Number</label>
                <input type="text" name="tax_number" value="{{ old('tax_number') }}" class="form-input">
            </div>

            <div class="sm:col-span-2">
                <label class="form-label">Address</label>
                <textarea name="address" rows="3" class="form-textarea">{{ old('address') }}</textarea>
            </div>

            <div class="sm:col-span-2 flex items-center gap-2">
                <input type="checkbox" name="is_active" id="is_active" value="1"
                       {{ old('is_active', '1') ? 'checked' : '' }}
                       class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                <label for="is_active" class="form-label mb-0">Active</label>
            </div>

        </div>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="btn-primary">Save Supplier</button>
            <a href="{{ route('purchase.suppliers.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
