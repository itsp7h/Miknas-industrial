<?php

namespace Database\Factories;

use App\Models\PurchaseRequest;
use App\Models\RfqInvitation;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierQuoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rfq_invitation_id' => RfqInvitation::factory(),
            'purchase_request_id' => PurchaseRequest::factory(),
            'supplier_id' => Supplier::factory(),
            'submitted_at' => now(),
        ];
    }
}
