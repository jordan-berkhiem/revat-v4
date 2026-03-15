<?php

namespace Database\Factories;

use App\Models\CampaignEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignEmail>
 */
class CampaignEmailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => 1,
            'external_id' => fake()->unique()->uuid(),
            'name' => fake()->words(3, true),
            'subject' => fake()->sentence(),
            'from_name' => fake()->name(),
            'from_email' => fake()->safeEmail(),
            'type' => 'regular',
            'sent' => fake()->numberBetween(100, 10000),
            'delivered' => fake()->numberBetween(90, 9000),
            'opens' => fake()->numberBetween(10, 5000),
            'clicks' => fake()->numberBetween(5, 2000),
            'sent_at' => fake()->dateTimeBetween('-30 days'),
        ];
    }
}
