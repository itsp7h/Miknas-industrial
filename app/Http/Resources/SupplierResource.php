<?php

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
            'secondary_email' => $this->secondary_email,
            'phone' => $this->phone,
            'phone2' => $this->phone2,
            'whatsapp_number' => $this->whatsapp_number,
            'whatsapp' => $this->whatsapp,
            'address' => $this->address,
            'website' => $this->website,
            'tax_number' => $this->tax_number,
            'credit_terms' => $this->credit_terms,
            'credit_days' => $this->credit_days,
            'is_active' => (bool) $this->is_active,
            'remarks' => $this->remarks,
        ];
    }
}
