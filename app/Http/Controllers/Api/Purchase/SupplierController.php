<?php

namespace App\Http\Controllers\Api\Purchase;

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

    public function show(Supplier $supplier)
    {
        return new SupplierResource($supplier);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_code' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'whatsapp_number' => 'nullable|string|max:20',
            'credit_days' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $supplier = Supplier::create(array_merge($data, [
            'is_active' => $data['is_active'] ?? true,
        ]));

        return (new SupplierResource($supplier))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'supplier_code' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'whatsapp_number' => 'nullable|string|max:20',
            'credit_days' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $supplier->update($data);

        return new SupplierResource($supplier);
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return response()->noContent();
    }
}
