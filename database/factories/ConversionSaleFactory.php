<?php

namespace Database\Factories;

use App\Models\ConversionSale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversionSale>
 */
class ConversionSaleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => 1,
            'external_id' => fake()->unique()->uuid(),
            'revenue' => fake()->randomFloat(2, 10, 500),
            'payout' => fake()->randomFloat(2, 5, 200),
            'cost' => fake()->randomFloat(2, 1, 50),
            'converted_at' => fake()->dateTimeBetween('-30 days'),
        ];
    }
}
