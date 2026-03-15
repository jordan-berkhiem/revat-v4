<?php

use App\Models\ExtractionBatch;
use App\Models\ExtractionRecord;
use App\Models\Organization;
use App\Models\Workspace;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->org = Organization::create(['name' => 'Test Org']);
    $this->workspace = new Workspace(['name' => 'Default']);
    $this->workspace->organization_id = $this->org->id;
    $this->workspace->is_default = true;
    $this->workspace->save();

    $this->integration = $this->workspace->integrations()->create([
        'name' => 'Test AC',
        'platform' => 'activecampaign',
    ]);
});

// ── Schema Tests ──────────────────────────────────────────────────────

it('creates extraction_batches table with correct columns', function () {
    expect(Schema::hasTable('extraction_batches'))->toBeTrue()
        ->and(Schema::hasColumns('extraction_batches', [
            'id', 'integration_id', 'workspace_id', 'data_type', 'status',
            'records_count', 'error', 'started_at', 'extracted_at',
            'transformed_at', 'completed_at', 'failed_at',
        ]))->toBeTrue();
});

it('creates extraction_records table with created_at only', function () {
    expect(Schema::hasTable('extraction_records'))->toBeTrue()
        ->and(Schema::hasColumns('extraction_records', [
            'id', 'extraction_batch_id', 'external_id', 'payload', 'created_at',
        ]))->toBeTrue();
});

// ── ExtractionBatch Tests ─────────────────────────────────────────────

it('creates a batch with default pending status', function () {
    $batch = ExtractionBatch::create([
        'integration_id' => $this->integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_emails',
    ]);

    expect($batch->status)->toBe('pending')
        ->and($batch->records_count)->toBe(0);
});

it('transitions through extraction status flow', function () {
    $batch = ExtractionBatch::create([
        'integration_id' => $this->integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_emails',
    ]);

    $batch->markExtracting();
    expect($batch->status)->toBe('extracting')
        ->and($batch->started_at)->not->toBeNull();

    $batch->markExtracted();
    expect($batch->status)->toBe('extracted')
        ->and($batch->extracted_at)->not->toBeNull();

    $batch->markTransforming();
    expect($batch->status)->toBe('transforming');

    $batch->markTransformed();
    expect($batch->status)->toBe('transformed')
        ->and($batch->transformed_at)->not->toBeNull();

    $batch->markCompleted();
    expect($batch->status)->toBe('completed')
        ->and($batch->completed_at)->not->toBeNull();
});

it('marks batch as failed with error', function () {
    $batch = ExtractionBatch::create([
        'integration_id' => $this->integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_emails',
    ]);

    $batch->markFailed('API timeout');

    expect($batch->status)->toBe('failed')
        ->and($batch->failed_at)->not->toBeNull()
        ->and($batch->error)->toBe('API timeout');
});

it('marks batch as transform failed', function () {
    $batch = ExtractionBatch::create([
        'integration_id' => $this->integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_emails',
    ]);

    $batch->markTransformFailed('Invalid data format');

    expect($batch->status)->toBe('transform_failed')
        ->and($batch->error)->toBe('Invalid data format');
});

it('has pending and failed scopes', function () {
    ExtractionBatch::create([
        'integration_id' => $this->integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_emails',
        'status' => 'pending',
    ]);

    $failed = ExtractionBatch::create([
        'integration_id' => $this->integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_email_clicks',
        'status' => 'failed',
    ]);

    expect(ExtractionBatch::pending()->count())->toBe(1)
        ->and(ExtractionBatch::failed()->count())->toBe(1);
});

it('has forIntegration scope', function () {
    ExtractionBatch::create([
        'integration_id' => $this->integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_emails',
    ]);

    expect(ExtractionBatch::forIntegration($this->integration->id)->count())->toBe(1)
        ->and(ExtractionBatch::forIntegration(999)->count())->toBe(0);
});

it('has integration relationship', function () {
    $batch = ExtractionBatch::create([
        'integration_id' => $this->integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_emails',
    ]);

    expect($batch->integration->id)->toBe($this->integration->id);
});

// ── ExtractionRecord Tests ────────────────────────────────────────────

it('creates extraction record with payload cast as array', function () {
    $batch = ExtractionBatch::create([
        'integration_id' => $this->integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_emails',
    ]);

    $record = ExtractionRecord::create([
        'extraction_batch_id' => $batch->id,
        'external_id' => 'ext-123',
        'payload' => ['subject' => 'Test Email', 'sent_count' => 100],
    ]);

    expect($record->payload)->toBe(['subject' => 'Test Email', 'sent_count' => 100])
        ->and($record->external_id)->toBe('ext-123');
});

it('has batch relationship', function () {
    $batch = ExtractionBatch::create([
        'integration_id' => $this->integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_emails',
    ]);

    $record = ExtractionRecord::create([
        'extraction_batch_id' => $batch->id,
        'external_id' => 'ext-456',
        'payload' => ['data' => 'test'],
    ]);

    expect($record->batch->id)->toBe($batch->id);
});

it('batch has records relationship', function () {
    $batch = ExtractionBatch::create([
        'integration_id' => $this->integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_emails',
    ]);

    ExtractionRecord::create(['extraction_batch_id' => $batch->id, 'external_id' => 'a', 'payload' => []]);
    ExtractionRecord::create(['extraction_batch_id' => $batch->id, 'external_id' => 'b', 'payload' => []]);

    expect($batch->records)->toHaveCount(2);
});

it('integration has extractionBatches relationship', function () {
    ExtractionBatch::create([
        'integration_id' => $this->integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_emails',
    ]);

    expect($this->integration->extractionBatches)->toHaveCount(1);
});

it('counts records when marking batch as extracted', function () {
    $batch = ExtractionBatch::create([
        'integration_id' => $this->integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_emails',
    ]);

    ExtractionRecord::create(['extraction_batch_id' => $batch->id, 'external_id' => '1', 'payload' => []]);
    ExtractionRecord::create(['extraction_batch_id' => $batch->id, 'external_id' => '2', 'payload' => []]);
    ExtractionRecord::create(['extraction_batch_id' => $batch->id, 'external_id' => '3', 'payload' => []]);

    $batch->markExtracted();

    expect($batch->records_count)->toBe(3);
});
