<?php

namespace Database\Factories;

use App\Models\SupplierQuote;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierQuoteItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supplier_quote_id' => SupplierQuote::factory(),
            'description'       => $this->faker->sentence(3),
            'quantity'          => 1,
            'unit_price'        => 10,
            'total_price'       => 10,
        ];
    }
}
