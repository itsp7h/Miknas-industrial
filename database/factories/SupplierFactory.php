<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supplier_code' => 'SUP-'.$this->faker->unique()->numberBetween(1000, 9999),
            'name' => $this->faker->company(),
            'category' => $this->faker->randomElement(['Raw Material', 'Fasteners', 'Equipment']),
            'email' => $this->faker->unique()->companyEmail(),
            'is_active' => true,
        ];
    }
}
