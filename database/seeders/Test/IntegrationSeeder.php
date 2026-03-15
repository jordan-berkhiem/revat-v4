<?php

namespace Database\Seeders\Test;

use App\Models\ExtractionBatch;
use App\Models\ExtractionRecord;
use App\Models\Integration;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class IntegrationSeeder extends Seeder
{
    /**
     * Seed 2 integrations per workspace (one campaign source, one conversion source)
     * with completed extraction batches and records.
     */
    public function run(): void
    {
        $platforms = ['activecampaign', 'voluum'];

        $workspaces = Workspace::orderBy('id')->get();

        foreach ($workspaces as $workspace) {
            // Campaign source integration
            $campaignIntegration = $this->createIntegration($workspace, 'Campaign Source', $platforms[0], ['campaign_emails', 'campaign_email_clicks']);

            // Conversion source integration
            $conversionIntegration = $this->createIntegration($workspace, 'Conversion Source', $platforms[1], ['conversion_sales']);

            // Create extraction batches for each data type
            $this->seedBatches($campaignIntegration, $workspace, 'campaign_emails', 5);
            $this->seedBatches($campaignIntegration, $workspace, 'campaign_email_clicks', 10);
            $this->seedBatches($conversionIntegration, $workspace, 'conversion_sales', 8);
        }
    }

    protected function seedBatches(Integration $integration, Workspace $workspace, string $dataType, int $recordCount): void
    {
        $batch = ExtractionBatch::create([
            'integration_id' => $integration->id,
            'workspace_id' => $workspace->id,
            'data_type' => $dataType,
            'status' => ExtractionBatch::STATUS_COMPLETED,
        ]);

        $batch->started_at = now()->subHours(2);
        $batch->extracted_at = now()->subHours(2)->addMinutes(5);
        $batch->transformed_at = now()->subHours(2)->addMinutes(10);
        $batch->completed_at = now()->subHours(2)->addMinutes(15);
        $batch->records_count = $recordCount;
        $batch->save();

        for ($i = 0; $i < $recordCount; $i++) {
            ExtractionRecord::create([
                'extraction_batch_id' => $batch->id,
                'external_id' => fake()->uuid(),
                'payload' => $this->generatePayload($dataType, $i),
            ]);
        }
    }

    protected function createIntegration(Workspace $workspace, string $name, string $platform, array $dataTypes): Integration
    {
        $integration = new Integration([
            'name' => $name,
            'platform' => $platform,
            'data_types' => $dataTypes,
            'is_active' => true,
            'sync_interval_minutes' => 60,
        ]);
        $integration->workspace_id = $workspace->id;
        $integration->organization_id = $workspace->organization_id;
        $integration->credentials = ['api_key' => 'test-key-'.fake()->uuid()];
        $integration->last_synced_at = now()->subHour();
        $integration->save();

        return $integration;
    }

    protected function generatePayload(string $dataType, int $index): array
    {
        return match ($dataType) {
            'campaign_emails' => [
                'external_id' => 'camp-'.($index + 1),
                'name' => fake()->words(3, true),
                'subject' => fake()->sentence(),
                'from_name' => fake()->name(),
                'from_email' => fake()->safeEmail(),
                'sent' => fake()->numberBetween(100, 5000),
                'opens' => fake()->numberBetween(10, 2000),
                'clicks' => fake()->numberBetween(5, 500),
            ],
            'campaign_email_clicks' => [
                'external_campaign_id' => 'camp-'.fake()->numberBetween(1, 5),
                'subscriber_email_hash' => hash('sha256', "subscriber{$index}@example.com"),
                'click_url' => fake()->url(),
                'url_params' => ['utm_source' => 'email', 'utm_campaign' => 'test'],
            ],
            'conversion_sales' => [
                'external_id' => 'conv-'.($index + 1),
                'revenue' => fake()->randomFloat(2, 10, 500),
                'payout' => fake()->randomFloat(2, 5, 200),
                'cost' => fake()->randomFloat(2, 1, 50),
                'converted_at' => now()->subDays(fake()->numberBetween(1, 30))->toIso8601String(),
            ],
            default => [],
        };
    }
}
