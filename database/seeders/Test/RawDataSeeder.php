<?php

namespace Database\Seeders\Test;

use App\Models\CampaignEmailClickRawData;
use App\Models\CampaignEmailRawData;
use App\Models\ConversionSaleRawData;
use App\Models\Integration;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class RawDataSeeder extends Seeder
{
    /**
     * Seed raw data tables linked to integrations.
     */
    public function run(): void
    {
        $workspaces = Workspace::orderBy('id')->get();

        foreach ($workspaces as $workspace) {
            $campaignIntegration = Integration::where('workspace_id', $workspace->id)
                ->where('platform', 'activecampaign')
                ->first();

            $conversionIntegration = Integration::where('workspace_id', $workspace->id)
                ->where('platform', 'voluum')
                ->first();

            if ($campaignIntegration) {
                $this->seedCampaignEmailRawData($workspace, $campaignIntegration, 20);
                $this->seedCampaignEmailClickRawData($workspace, $campaignIntegration, 50);
            }

            if ($conversionIntegration) {
                $this->seedConversionSaleRawData($workspace, $conversionIntegration, 30);
            }
        }
    }

    protected function seedCampaignEmailRawData(Workspace $workspace, Integration $integration, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            CampaignEmailRawData::create([
                'workspace_id' => $workspace->id,
                'integration_id' => $integration->id,
                'external_id' => "raw-camp-{$workspace->id}-{$i}",
                'raw_data' => [
                    'external_id' => "raw-camp-{$workspace->id}-{$i}",
                    'name' => fake()->words(3, true),
                    'subject' => fake()->sentence(),
                    'from_name' => fake()->name(),
                    'from_email' => fake()->safeEmail(),
                    'sent' => fake()->numberBetween(100, 5000),
                    'delivered' => fake()->numberBetween(90, 4500),
                    'opens' => fake()->numberBetween(10, 2000),
                    'clicks' => fake()->numberBetween(5, 500),
                    'sent_at' => now()->subDays(fake()->numberBetween(1, 30))->toIso8601String(),
                ],
                'content_hash' => hash('xxh128', "camp-{$workspace->id}-{$i}"),
            ]);
        }
    }

    protected function seedCampaignEmailClickRawData(Workspace $workspace, Integration $integration, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            CampaignEmailClickRawData::create([
                'workspace_id' => $workspace->id,
                'integration_id' => $integration->id,
                'external_campaign_id' => "raw-camp-{$workspace->id}-".fake()->numberBetween(0, 19),
                'subscriber_email_hash' => hash('sha256', "subscriber{$i}@example.com"),
                'clicked_url' => fake()->url(),
                'url_params' => ['utm_source' => 'email', 'utm_campaign' => 'test-'.$i],
                'raw_data' => [
                    'external_campaign_id' => "raw-camp-{$workspace->id}-".fake()->numberBetween(0, 19),
                    'subscriber_email_hash' => hash('sha256', "subscriber{$i}@example.com"),
                    'click_url' => fake()->url(),
                    'url_params' => ['utm_source' => 'email'],
                ],
                'content_hash' => hash('xxh128', "click-{$workspace->id}-{$i}"),
            ]);
        }
    }

    protected function seedConversionSaleRawData(Workspace $workspace, Integration $integration, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            ConversionSaleRawData::create([
                'workspace_id' => $workspace->id,
                'integration_id' => $integration->id,
                'external_id' => "raw-conv-{$workspace->id}-{$i}",
                'raw_data' => [
                    'external_id' => "raw-conv-{$workspace->id}-{$i}",
                    'revenue' => fake()->randomFloat(2, 10, 500),
                    'payout' => fake()->randomFloat(2, 5, 200),
                    'cost' => fake()->randomFloat(2, 1, 50),
                    'converted_at' => now()->subDays(fake()->numberBetween(1, 30))->toIso8601String(),
                ],
                'content_hash' => hash('xxh128', "conv-{$workspace->id}-{$i}"),
            ]);
        }
    }
}
