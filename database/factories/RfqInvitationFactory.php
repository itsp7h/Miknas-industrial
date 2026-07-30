<?php

namespace Database\Factories;

use App\Models\PurchaseRequest;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class RfqInvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'purchase_request_id' => PurchaseRequest::factory(),
            'supplier_id'         => Supplier::factory(),
            'token'               => $this->faker->unique()->uuid(),
            'channel'             => 'email',
            'status'              => 'submitted',
        ];
    }
}
