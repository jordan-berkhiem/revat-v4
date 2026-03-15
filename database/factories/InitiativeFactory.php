<?php

namespace Database\Factories;

use App\Models\Initiative;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Initiative>
 */
class InitiativeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'workspace_id' => fn (array $attrs) => Program::find($attrs['program_id'])->workspace_id,
            'name' => fake()->words(3, true),
            'code' => strtoupper(fake()->unique()->lexify('???')),
        ];
    }
}
