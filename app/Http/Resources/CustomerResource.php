<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'contact_person' => $this->contact_person,
            'email' => $this->email,
            'phone' => $this->phone,
            'whatsapp_number' => $this->whatsapp_number,
            'address' => $this->address,
            'tax_number' => $this->tax_number,
            'credit_limit' => $this->credit_limit,
            'outstanding_balance' => $this->outstanding_balance,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
