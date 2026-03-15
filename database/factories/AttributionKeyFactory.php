<?php

namespace Database\Factories;

use App\Models\AttributionConnector;
use App\Models\AttributionKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttributionKey>
 */
class AttributionKeyFactory extends Factory
{
    public function definition(): array
    {
        $value = fake()->unique()->safeEmail();

        return [
            'workspace_id' => 1,
            'connector_id' => AttributionConnector::factory(),
            'key_hash' => hash('sha256', $value),
            'key_value' => $value,
        ];
    }
}
