<?php

namespace Database\Factories;

use App\Models\CampaignEmail;
use App\Models\CampaignEmailClick;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignEmailClick>
 */
class CampaignEmailClickFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => 1,
            'campaign_email_id' => CampaignEmail::factory(),
            'clicked_at' => fake()->dateTimeBetween('-30 days'),
        ];
    }
}
