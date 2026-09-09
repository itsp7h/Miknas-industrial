<?php

namespace App\Http\Controllers\Api\Sales;

use App\Events\CustomerDeleted;
use App\Events\CustomerSaved;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        return CustomerResource::collection(Customer::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $customer = Customer::create($this->validated($request));

        event(new CustomerSaved($customer));

        return (new CustomerResource($customer))->response()->setStatusCode(201);
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validated($request));

        event(new CustomerSaved($customer));

        return new CustomerResource($customer);
    }

    public function destroy(Customer $customer)
    {
        // Orders and invoices reference the customer; removing one with trading
        // history would orphan them, so it is deactivated instead.
        if ($customer->salesOrders()->exists() || $customer->salesInvoices()->exists()) {
            $customer->update(['is_active' => false]);
            event(new CustomerSaved($customer));

            return response()->json([
                'message' => 'Customer has sales history, so it was deactivated rather than deleted.',
                'deactivated' => true,
            ]);
        }

        $id = $customer->id;
        $customer->delete();

        event(new CustomerDeleted($id));

        return response()->json(['deleted' => true]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'whatsapp_number' => 'nullable|string|max:30',
            'address' => 'nullable|string',
            'tax_number' => 'nullable|string|max:100',
            'credit_limit' => 'nullable|numeric|min:0',
        ]);

        // credit_limit is NOT NULL with a 0 default; an omitted value must
        // become 0 rather than a null that fails the insert.
        $data['credit_limit'] = $data['credit_limit'] ?? 0;
        $data['is_active'] = (bool) $request->input('is_active', true);

        return $data;
    }
}
