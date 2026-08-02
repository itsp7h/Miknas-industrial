<?php
// app/Http/Resources/SupplierResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_code' => $this->supplier_code,
            'name' => $this->name,
            'category' => $this->category,
            'contact_person' => $this->contact_person,
            'email' => $this->email,
            'phone' => $this->phone,
            'whatsapp_number' => $this->whatsapp_number,
            'address' => $this->address,
            'credit_days' => $this->credit_days,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
