<?php

use App\Models\ExtractionBatch;
use App\Models\ExtractionRecord;
use App\Models\Organization;
use App\Models\Workspace;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::create(['name' => 'Extraction Test Org']);
    $this->workspace = new Workspace(['name' => 'Default']);
    $this->workspace->organization_id = $this->org->id;
    $this->workspace->is_default = true;
    $this->workspace->save();
});

it('creates extraction batch and records with correct linkage', function () {
    $integration = createIntegration([
        'workspace_id' => $this->workspace->id,
        'organization_id' => $this->org->id,
        'name' => 'Test Integration',
        'platform' => 'activecampaign',
        'data_types' => ['campaign_emails'],
        'is_active' => true,
        'sync_interval_minutes' => 60,
        'credentials' => ['api_key' => 'test-key'],
    ]);

    $batch = ExtractionBatch::create([
        'integration_id' => $integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_emails',
        'status' => ExtractionBatch::STATUS_PENDING,
    ]);

    expect($batch->status)->toBe(ExtractionBatch::STATUS_PENDING);
    expect($batch->workspace_id)->toBe($this->workspace->id);
    expect($batch->integration_id)->toBe($integration->id);

    // Create extraction records linked to batch
    $records = [];
    for ($i = 0; $i < 5; $i++) {
        $records[] = ExtractionRecord::create([
            'extraction_batch_id' => $batch->id,
            'external_id' => "ext-{$i}",
            'payload' => ['external_id' => "ext-{$i}", 'name' => "Campaign {$i}"],
        ]);
    }

    expect(ExtractionRecord::where('extraction_batch_id', $batch->id)->count())->toBe(5);

    // Transition batch through statuses
    $batch->markExtracting();
    expect($batch->fresh()->status)->toBe(ExtractionBatch::STATUS_EXTRACTING);

    $batch->markExtracted();
    expect($batch->fresh()->status)->toBe(ExtractionBatch::STATUS_EXTRACTED);
});

it('transitions batch to completed status', function () {
    $integration = createIntegration([
        'workspace_id' => $this->workspace->id,
        'organization_id' => $this->org->id,
        'name' => 'Test Integration',
        'platform' => 'voluum',
        'data_types' => ['conversion_sales'],
        'is_active' => true,
        'sync_interval_minutes' => 60,
        'credentials' => ['api_key' => 'test-key'],
    ]);

    $batch = ExtractionBatch::create([
        'integration_id' => $integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'conversion_sales',
        'status' => ExtractionBatch::STATUS_PENDING,
    ]);

    $batch->markExtracting();
    $batch->markExtracted();
    $batch->markTransforming();
    $batch->markTransformed();
    $batch->markCompleted();

    $fresh = $batch->fresh();
    expect($fresh->status)->toBe(ExtractionBatch::STATUS_COMPLETED);
    expect($fresh->completed_at)->not->toBeNull();
});

it('links records to correct batch', function () {
    $integration = createIntegration([
        'workspace_id' => $this->workspace->id,
        'organization_id' => $this->org->id,
        'name' => 'Test Integration',
        'platform' => 'activecampaign',
        'data_types' => ['campaign_emails'],
        'is_active' => true,
        'sync_interval_minutes' => 60,
        'credentials' => ['api_key' => 'test-key'],
    ]);

    $batch1 = ExtractionBatch::create([
        'integration_id' => $integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_emails',
    ]);

    $batch2 = ExtractionBatch::create([
        'integration_id' => $integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_email_clicks',
    ]);

    ExtractionRecord::create([
        'extraction_batch_id' => $batch1->id,
        'external_id' => 'rec-1',
        'payload' => ['data' => 'batch1'],
    ]);

    ExtractionRecord::create([
        'extraction_batch_id' => $batch2->id,
        'external_id' => 'rec-2',
        'payload' => ['data' => 'batch2'],
    ]);

    expect($batch1->records()->count())->toBe(1);
    expect($batch2->records()->count())->toBe(1);
    expect($batch1->records()->first()->payload)->toBe(['data' => 'batch1']);
});
