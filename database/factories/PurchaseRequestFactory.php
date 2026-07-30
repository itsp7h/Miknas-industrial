<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'request_number'    => 'MPR-' . $this->faker->unique()->numberBetween(1000, 9999),
            'date'              => now(),
            'project_name'      => $this->faker->word(),
            'requested_by_name' => $this->faker->name(),
            'status'            => 'pending',
            'stage'             => 'draft',
            'requested_by'      => User::factory(),
        ];
    }
}
