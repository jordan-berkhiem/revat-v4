<?php

namespace Database\Factories;

use App\Models\AttributionConnector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttributionConnector>
 */
class AttributionConnectorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => 1,
            'name' => fake()->words(2, true).' Connector',
            'campaign_integration_id' => fake()->randomNumber(3),
            'campaign_data_type' => 'email',
            'conversion_integration_id' => fake()->randomNumber(3),
            'conversion_data_type' => 'sale',
            'field_mappings' => [['campaign' => 'from_email', 'conversion' => 'external_id']],
            'is_active' => true,
        ];
    }
}
