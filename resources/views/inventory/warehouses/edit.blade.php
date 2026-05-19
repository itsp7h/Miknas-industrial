@extends('layouts.app')

@section('title', 'Edit Warehouse')

@section('content')
<div class="mb-6">
    <h1 class="page-title">Edit Warehouse</h1>
    <p class="page-subtitle"><a href="{{ route('inventory.warehouses.index') }}" class="text-blue-600 hover:underline">Warehouses</a> / Edit</p>
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
    <form action="{{ route('inventory.warehouses.update', $warehouse) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

            <div>
                <label class="form-label">Code <span class="text-red-500">*</span></label>
                <input type="text" name="code" value="{{ old('code', $warehouse->code) }}" required class="form-input">
            </div>

            <div>
                <label class="form-label">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $warehouse->name) }}" required class="form-input">
            </div>

            <div class="sm:col-span-2">
                <label class="form-label">Location</label>
                <input type="text" name="location" value="{{ old('location', $warehouse->location) }}" class="form-input">
            </div>

            <div class="sm:col-span-2">
                <label class="form-label">Description</label>
                <textarea name="description" rows="3" class="form-textarea">{{ old('description', $warehouse->description) }}</textarea>
            </div>

            <div class="sm:col-span-2 flex items-center gap-2">
                <input type="checkbox" name="is_active" id="is_active" value="1"
                       {{ old('is_active', $warehouse->is_active) ? 'checked' : '' }}
                       class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                <label for="is_active" class="form-label mb-0">Active</label>
            </div>

        </div>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="btn-primary">Update Warehouse</button>
            <a href="{{ route('inventory.warehouses.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
