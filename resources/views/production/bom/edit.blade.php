@extends('layouts.app')

@section('title', 'Edit BOM Entry')

@section('content')
<div class="mb-6">
    <h1 class="page-title">Edit BOM Entry</h1>
    <p class="page-subtitle"><a href="{{ route('production.bom.index') }}" class="text-blue-600 hover:underline">Bill of Materials</a> / Edit</p>
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
    <form action="{{ route('production.bom.update', $bom) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

            <div class="sm:col-span-2">
                <label class="form-label">Product (Finished Good) <span class="text-red-500">*</span></label>
                <select name="product_id" required class="form-select">
                    <option value="">-- Select Product --</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" {{ old('product_id', $bom->product_id) == $product->id ? 'selected' : '' }}>
                            {{ $product->item_code }} - {{ $product->item_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-2">
                <label class="form-label">Raw Material <span class="text-red-500">*</span></label>
                <select name="raw_material_id" required class="form-select">
                    <option value="">-- Select Raw Material --</option>
                    @foreach($rawMaterials as $material)
                        <option value="{{ $material->id }}" {{ old('raw_material_id', $bom->raw_material_id) == $material->id ? 'selected' : '' }}>
                            {{ $material->item_code }} - {{ $material->item_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Quantity Required <span class="text-red-500">*</span></label>
                <input type="number" name="quantity_required" value="{{ old('quantity_required', $bom->quantity_required) }}" min="0.001" step="0.001" required class="form-input">
            </div>

            <div>
                <label class="form-label">Unit of Measure <span class="text-red-500">*</span></label>
                <input type="text" name="unit_of_measure" value="{{ old('unit_of_measure', $bom->unit_of_measure) }}" required class="form-input">
            </div>

        </div>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="btn-primary">Update BOM Entry</button>
            <a href="{{ route('production.bom.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
