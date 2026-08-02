<?php
// app/Http/Controllers/Api/Purchase/SupplierController.php

namespace App\Http\Controllers\Api\Purchase;

use App\Events\SupplierSaved;
use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        return SupplierResource::collection(Supplier::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'supplier_code' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'credit_days' => 'nullable|integer|min:0',
        ]);

        $supplier = Supplier::create(array_merge($data, [
            'is_active' => (bool) $request->input('is_active', true),
        ]));

        event(new SupplierSaved($supplier));

        return (new SupplierResource($supplier))->response()->setStatusCode(201);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'supplier_code' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'credit_days' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $supplier->update($data);

        event(new SupplierSaved($supplier));

        return new SupplierResource($supplier);
    }
}
