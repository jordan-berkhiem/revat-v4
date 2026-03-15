<?php

use App\Models\CampaignEmail;
use App\Models\CampaignEmailRawData;
use App\Models\ExtractionBatch;
use App\Models\Integration;
use App\Models\Organization;
use App\Models\Workspace;
use App\Services\Transformation\CampaignEmailTransformer;
use App\Services\Transformation\ChangeDetector;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->org = Organization::create(['name' => 'Test Org']);
    $this->workspace = new Workspace(['name' => 'Default']);
    $this->workspace->organization_id = $this->org->id;
    $this->workspace->is_default = true;
    $this->workspace->save();

    $this->integration = new Integration([
        'name' => 'Test Integration',
        'platform' => 'activecampaign',
        'data_types' => ['campaign_emails'],
        'is_active' => true,
        'sync_interval_minutes' => 60,
    ]);
    $this->integration->workspace_id = $this->workspace->id;
    $this->integration->organization_id = $this->org->id;
    $this->integration->save();

    $this->batch = ExtractionBatch::create([
        'integration_id' => $this->integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_emails',
        'status' => ExtractionBatch::STATUS_EXTRACTED,
    ]);

    $this->changeDetector = new ChangeDetector;
    $this->transformer = new CampaignEmailTransformer($this->changeDetector);
});

// ── ChangeDetector: New Records ───────────────────────────────────────

it('identifies new records with null content_hash as changed', function () {
    $record = CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'new-1',
        'raw_data' => ['name' => 'Test'],
    ]);

    expect($record->content_hash)->toBeNull();
    expect($this->changeDetector->hasChanged($record))->toBeTrue();
});

// ── ChangeDetector: Changed Records ───────────────────────────────────

it('identifies changed records with hash mismatch', function () {
    $record = CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'changed-1',
        'raw_data' => ['name' => 'Original'],
    ]);

    // Mark as processed with current data
    $this->changeDetector->markProcessed($record);
    expect($this->changeDetector->hasChanged($record))->toBeFalse();

    // Update raw data
    $record->raw_data = ['name' => 'Updated'];
    $record->save();

    expect($this->changeDetector->hasChanged($record))->toBeTrue();
});

// ── ChangeDetector: Unchanged Records ─────────────────────────────────

it('identifies unchanged records with matching hash', function () {
    $record = CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'unchanged-1',
        'raw_data' => ['name' => 'Same'],
    ]);

    $this->changeDetector->markProcessed($record);

    expect($this->changeDetector->hasChanged($record))->toBeFalse();
});

// ── Hash Determinism ──────────────────────────────────────────────────

it('computes deterministic hash regardless of key ordering', function () {
    $hash1 = $this->changeDetector->computeHash(['b' => 2, 'a' => 1, 'c' => 3]);
    $hash2 = $this->changeDetector->computeHash(['a' => 1, 'c' => 3, 'b' => 2]);
    $hash3 = $this->changeDetector->computeHash(['c' => 3, 'b' => 2, 'a' => 1]);

    expect($hash1)->toBe($hash2)->toBe($hash3);
    expect(strlen($hash1))->toBe(64); // SHA-256 hex = 64 chars
});

it('computes deterministic hash for nested arrays', function () {
    $hash1 = $this->changeDetector->computeHash(['outer' => ['z' => 3, 'a' => 1], 'top' => 'val']);
    $hash2 = $this->changeDetector->computeHash(['top' => 'val', 'outer' => ['a' => 1, 'z' => 3]]);

    expect($hash1)->toBe($hash2);
});

// ── Transformer Skips Unchanged ───────────────────────────────────────

it('skips unchanged records and reports correct skip count', function () {
    $rawRecord = CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'skip-1',
        'raw_data' => [
            'campaignid' => 'skip-1',
            'name' => 'Test Campaign',
            'total_sent' => 100,
        ],
    ]);

    // First transformation: should process
    $result1 = $this->transformer->transform($this->batch);
    expect($result1->created)->toBe(1);
    expect($result1->skipped)->toBe(0);
    expect(CampaignEmail::count())->toBe(1);

    // Second transformation without changes: should skip
    $result2 = $this->transformer->transform($this->batch);
    expect($result2->created)->toBe(0);
    expect($result2->updated)->toBe(0);
    expect($result2->skipped)->toBe(1);
    expect(CampaignEmail::count())->toBe(1);
});

// ── Force Mode Bypasses Change Detection ──────────────────────────────

it('processes all records when force mode is enabled', function () {
    $rawRecord = CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'force-1',
        'raw_data' => [
            'campaignid' => 'force-1',
            'name' => 'Force Test',
            'total_sent' => 100,
        ],
    ]);

    // First transformation
    $result1 = $this->transformer->transform($this->batch);
    expect($result1->created)->toBe(1);

    // Force re-transformation (no changes, but force bypasses detection)
    $this->transformer->setForce(true);
    $result2 = $this->transformer->transform($this->batch);
    expect($result2->updated)->toBe(1);
    expect($result2->skipped)->toBe(0);
});

// ── force_transform Flag on Batch ─────────────────────────────────────

it('processes all records when batch has force_transform flag', function () {
    $rawRecord = CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'batch-force-1',
        'raw_data' => [
            'campaignid' => 'batch-force-1',
            'name' => 'Batch Force Test',
            'total_sent' => 100,
        ],
    ]);

    // First transformation
    $result1 = $this->transformer->transform($this->batch);
    expect($result1->created)->toBe(1);

    // Set force_transform on batch
    $this->batch->force_transform = true;
    $this->batch->save();

    // Re-transform: should process despite no changes
    $result2 = $this->transformer->transform($this->batch);
    expect($result2->updated)->toBe(1);
    expect($result2->skipped)->toBe(0);
});

// ── Migration: content_hash Column ────────────────────────────────────

it('adds content_hash column to raw data tables', function () {
    expect(Schema::hasColumn('campaign_email_raw_data', 'content_hash'))->toBeTrue();
    expect(Schema::hasColumn('campaign_email_click_raw_data', 'content_hash'))->toBeTrue();
    expect(Schema::hasColumn('conversion_sale_raw_data', 'content_hash'))->toBeTrue();
});

// ── Migration: force_transform Column ─────────────────────────────────

it('adds force_transform column to extraction_batches', function () {
    expect(Schema::hasColumn('extraction_batches', 'force_transform'))->toBeTrue();

    // Default is false
    $batch = ExtractionBatch::create([
        'integration_id' => $this->integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_emails',
        'status' => ExtractionBatch::STATUS_EXTRACTED,
    ]);

    expect($batch->force_transform)->toBeFalse();
});
