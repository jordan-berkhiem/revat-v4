<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'slug' => fake()->unique()->slug(2),
            'max_workspaces' => 1,
            'max_integrations_per_workspace' => 2,
            'max_users' => 1,
            'is_visible' => true,
            'sort_order' => 0,
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => [
            'name' => 'Free',
            'slug' => 'free',
            'max_workspaces' => 1,
            'max_integrations_per_workspace' => 1,
            'max_users' => 1,
            'sort_order' => 0,
        ]);
    }

    public function starter(): static
    {
        return $this->state(fn () => [
            'name' => 'Starter',
            'slug' => 'starter',
            'max_workspaces' => 2,
            'max_integrations_per_workspace' => 3,
            'max_users' => 3,
            'sort_order' => 1,
        ]);
    }

    public function growth(): static
    {
        return $this->state(fn () => [
            'name' => 'Growth',
            'slug' => 'growth',
            'max_workspaces' => 5,
            'max_integrations_per_workspace' => 5,
            'max_users' => 10,
            'sort_order' => 2,
        ]);
    }

    public function agency(): static
    {
        return $this->state(fn () => [
            'name' => 'Agency',
            'slug' => 'agency',
            'max_workspaces' => 20,
            'max_integrations_per_workspace' => 10,
            'max_users' => 50,
            'sort_order' => 3,
        ]);
    }
}
