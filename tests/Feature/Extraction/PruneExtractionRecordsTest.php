<?php

use App\Models\ExtractionBatch;
use App\Models\ExtractionRecord;
use App\Models\Integration;
use App\Models\Organization;
use App\Models\Workspace;

beforeEach(function () {
    $this->org = Organization::create(['name' => 'Test Org']);
    $this->workspace = new Workspace(['name' => 'Default']);
    $this->workspace->organization_id = $this->org->id;
    $this->workspace->is_default = true;
    $this->workspace->save();

    $this->integration = new Integration([
        'name' => 'Test Integration',
        'platform' => 'activecampaign',
        'is_active' => true,
        'data_types' => ['campaign_emails'],
        'sync_interval_minutes' => 60,
    ]);
    $this->integration->workspace_id = $this->workspace->id;
    $this->integration->organization_id = $this->org->id;
    $this->integration->save();
    $this->integration->credentials = [
        'api_url' => 'https://test.api-us1.com',
        'api_key' => 'test-key',
    ];
    $this->integration->save();
});

it('prunes records for completed batches', function () {
    $batch = ExtractionBatch::create([
        'integration_id' => $this->integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_emails',
        'status' => ExtractionBatch::STATUS_COMPLETED,
    ]);

    ExtractionRecord::insert([
        ['extraction_batch_id' => $batch->id, 'external_id' => 'ext-001', 'payload' => '{}', 'created_at' => now()],
        ['extraction_batch_id' => $batch->id, 'external_id' => 'ext-002', 'payload' => '{}', 'created_at' => now()],
    ]);

    expect(ExtractionRecord::count())->toBe(2);

    $this->artisan('extraction:prune')->assertSuccessful();

    expect(ExtractionRecord::count())->toBe(0);

    // Batch itself should remain
    expect(ExtractionBatch::find($batch->id))->not->toBeNull();
});

it('prunes records for transformed batches', function () {
    $batch = ExtractionBatch::create([
        'integration_id' => $this->integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_emails',
        'status' => ExtractionBatch::STATUS_TRANSFORMED,
    ]);

    ExtractionRecord::insert([
        ['extraction_batch_id' => $batch->id, 'external_id' => 'ext-001', 'payload' => '{}', 'created_at' => now()],
    ]);

    $this->artisan('extraction:prune')->assertSuccessful();

    expect(ExtractionRecord::count())->toBe(0);
});

it('does not prune records for in-progress batches within TTL', function () {
    $batch = ExtractionBatch::create([
        'integration_id' => $this->integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_emails',
        'status' => ExtractionBatch::STATUS_EXTRACTING,
    ]);

    ExtractionRecord::insert([
        ['extraction_batch_id' => $batch->id, 'external_id' => 'ext-001', 'payload' => '{}', 'created_at' => now()],
    ]);

    $this->artisan('extraction:prune')->assertSuccessful();

    expect(ExtractionRecord::count())->toBe(1);
});

it('prunes old records past TTL regardless of batch status', function () {
    $batch = ExtractionBatch::create([
        'integration_id' => $this->integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_emails',
        'status' => ExtractionBatch::STATUS_EXTRACTING,
    ]);

    // Insert records that are 25 hours old (past 24h TTL)
    ExtractionRecord::insert([
        ['extraction_batch_id' => $batch->id, 'external_id' => 'ext-old', 'payload' => '{}', 'created_at' => now()->subHours(25)],
    ]);

    // Insert a fresh record
    ExtractionRecord::insert([
        ['extraction_batch_id' => $batch->id, 'external_id' => 'ext-new', 'payload' => '{}', 'created_at' => now()],
    ]);

    $this->artisan('extraction:prune')->assertSuccessful();

    expect(ExtractionRecord::count())->toBe(1);
    expect(ExtractionRecord::first()->external_id)->toBe('ext-new');
});

it('respects custom TTL option', function () {
    $batch = ExtractionBatch::create([
        'integration_id' => $this->integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_emails',
        'status' => ExtractionBatch::STATUS_EXTRACTING,
    ]);

    ExtractionRecord::insert([
        ['extraction_batch_id' => $batch->id, 'external_id' => 'ext-001', 'payload' => '{}', 'created_at' => now()->subHours(2)],
    ]);

    // With 1-hour TTL, the 2-hour-old record should be pruned
    $this->artisan('extraction:prune', ['--ttl' => 1])->assertSuccessful();

    expect(ExtractionRecord::count())->toBe(0);
});

it('outputs count of pruned records', function () {
    $batch = ExtractionBatch::create([
        'integration_id' => $this->integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_emails',
        'status' => ExtractionBatch::STATUS_COMPLETED,
    ]);

    ExtractionRecord::insert([
        ['extraction_batch_id' => $batch->id, 'external_id' => 'ext-001', 'payload' => '{}', 'created_at' => now()],
        ['extraction_batch_id' => $batch->id, 'external_id' => 'ext-002', 'payload' => '{}', 'created_at' => now()],
        ['extraction_batch_id' => $batch->id, 'external_id' => 'ext-003', 'payload' => '{}', 'created_at' => now()],
    ]);

    $this->artisan('extraction:prune')
        ->expectsOutput('Pruned 3 extraction records.')
        ->assertSuccessful();
});
