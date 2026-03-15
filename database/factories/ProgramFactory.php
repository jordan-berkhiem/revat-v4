<?php

namespace Database\Factories;

use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => 1,
            'name' => fake()->words(3, true),
            'code' => strtoupper(fake()->unique()->lexify('???')),
        ];
    }
}
