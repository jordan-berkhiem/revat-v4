<?php

namespace Database\Factories;

use App\Models\Effort;
use App\Models\Initiative;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Effort>
 */
class EffortFactory extends Factory
{
    public function definition(): array
    {
        return [
            'initiative_id' => Initiative::factory(),
            'workspace_id' => fn (array $attrs) => Initiative::find($attrs['initiative_id'])->workspace_id,
            'name' => fake()->words(2, true),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'channel_type' => 'email',
            'status' => 'active',
        ];
    }
}
