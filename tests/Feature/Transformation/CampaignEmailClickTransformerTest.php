<?php

use App\Models\Archives\ArchiveCampaignEmailClick;
use App\Models\CampaignEmail;
use App\Models\CampaignEmailClick;
use App\Models\CampaignEmailClickRawData;
use App\Models\ExtractionBatch;
use App\Models\IdentityHash;
use App\Models\Integration;
use App\Models\Organization;
use App\Models\Workspace;
use App\Services\Transformation\CampaignEmailClickTransformer;
use App\Services\Transformation\ChangeDetector;
use App\Services\Transformation\IdentityHashingService;

beforeEach(function () {
    $this->org = Organization::create(['name' => 'Test Org']);
    $this->workspace = new Workspace(['name' => 'Default']);
    $this->workspace->organization_id = $this->org->id;
    $this->workspace->is_default = true;
    $this->workspace->save();

    $this->integration = new Integration([
        'name' => 'Test Integration',
        'platform' => 'activecampaign',
        'data_types' => ['campaign_emails', 'campaign_email_clicks'],
        'is_active' => true,
        'sync_interval_minutes' => 60,
    ]);
    $this->integration->workspace_id = $this->workspace->id;
    $this->integration->organization_id = $this->org->id;
    $this->integration->save();

    $this->batch = ExtractionBatch::create([
        'integration_id' => $this->integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'campaign_email_clicks',
        'status' => ExtractionBatch::STATUS_EXTRACTED,
    ]);

    $this->transformer = new CampaignEmailClickTransformer(new IdentityHashingService, new ChangeDetector);
});

// ── Click Transformation with Resolved Campaign Email ─────────────────

it('transforms click with resolved campaign_email_id', function () {
    // Create the parent campaign email
    $campaignEmail = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'campaign-100',
        'name' => 'Test Campaign',
    ]);

    CampaignEmailClickRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_campaign_id' => 'campaign-100',
        'subscriber_email_hash' => str_repeat('a', 64),
        'raw_data' => [
            'email' => 'user@example.com',
            'clicked_at' => '2026-03-01T12:00:00Z',
        ],
    ]);

    $result = $this->transformer->transform($this->batch);

    expect($result->created)->toBe(1);
    expect($result->errors)->toBeEmpty();

    $click = CampaignEmailClick::first();
    expect($click->campaign_email_id)->toBe($campaignEmail->id);
    expect($click->identity_hash_id)->not->toBeNull();
    expect($click->clicked_at)->not->toBeNull();
    expect($click->extraction_batch_id)->toBe($this->batch->id);
    expect($click->transformed_at)->not->toBeNull();
    expect($click->raw_data_id)->not->toBeNull();
});

// ── Click Without Parent Campaign Email ───────────────────────────────

it('sets campaign_email_id to NULL when parent campaign does not exist', function () {
    CampaignEmailClickRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_campaign_id' => 'nonexistent-campaign',
        'subscriber_email_hash' => str_repeat('b', 64),
        'raw_data' => [
            'email' => 'user@example.com',
            'clicked_at' => '2026-03-01T12:00:00Z',
        ],
    ]);

    $result = $this->transformer->transform($this->batch);

    expect($result->created)->toBe(1);

    $click = CampaignEmailClick::first();
    expect($click->campaign_email_id)->toBeNull();
});

// ── Identity Hash Resolution ──────────────────────────────────────────

it('resolves identity_hash_id from email in raw data', function () {
    CampaignEmailClickRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_campaign_id' => 'campaign-200',
        'subscriber_email_hash' => str_repeat('c', 64),
        'raw_data' => [
            'email' => 'subscriber@example.com',
            'clicked_at' => '2026-03-01T12:00:00Z',
        ],
    ]);

    $this->transformer->transform($this->batch);

    $click = CampaignEmailClick::first();
    expect($click->identity_hash_id)->not->toBeNull();

    $identityHash = IdentityHash::find($click->identity_hash_id);
    expect($identityHash)->not->toBeNull();
    expect($identityHash->workspace_id)->toBe($this->workspace->id);
    expect($identityHash->type)->toBe('email');
});

// ── No Email Available ────────────────────────────────────────────────

it('sets identity_hash_id to NULL when no email is available', function () {
    CampaignEmailClickRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_campaign_id' => 'campaign-300',
        'subscriber_email_hash' => str_repeat('d', 64),
        'raw_data' => [
            'clicked_at' => '2026-03-01T12:00:00Z',
            // No email field
        ],
    ]);

    $this->transformer->transform($this->batch);

    $click = CampaignEmailClick::first();
    expect($click->identity_hash_id)->toBeNull();
});

// ── Upsert Behavior ──────────────────────────────────────────────────

it('updates existing click on re-transformation without duplicating', function () {
    $rawData = CampaignEmailClickRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_campaign_id' => 'campaign-400',
        'subscriber_email_hash' => str_repeat('e', 64),
        'raw_data' => [
            'email' => 'user@example.com',
            'clicked_at' => '2026-03-01T12:00:00Z',
        ],
    ]);

    // First transformation
    $result1 = $this->transformer->transform($this->batch);
    expect($result1->created)->toBe(1);
    expect(CampaignEmailClick::count())->toBe(1);

    // Update raw data to trigger change detection, then re-transform
    $rawData->update([
        'raw_data' => [
            'email' => 'user@example.com',
            'clicked_at' => '2026-03-02T12:00:00Z',
        ],
    ]);

    $result2 = $this->transformer->transform($this->batch);
    expect($result2->updated)->toBe(1);
    expect($result2->created)->toBe(0);
    expect(CampaignEmailClick::count())->toBe(1);
});

// ── Archive Creation ──────────────────────────────────────────────────

it('creates archive snapshot during transformation', function () {
    CampaignEmailClickRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_campaign_id' => 'campaign-500',
        'subscriber_email_hash' => str_repeat('f', 64),
        'raw_data' => [
            'email' => 'user@example.com',
            'clicked_at' => '2026-03-01',
            'extra_field' => 'preserved',
        ],
    ]);

    $this->transformer->transform($this->batch);

    expect(ArchiveCampaignEmailClick::count())->toBe(1);

    $archive = ArchiveCampaignEmailClick::first();
    expect($archive->workspace_id)->toBe($this->workspace->id);
    expect($archive->extraction_batch_id)->toBe($this->batch->id);
    expect($archive->payload['extra_field'])->toBe('preserved');
});

// ── Chunked Processing ───────────────────────────────────────────────

it('processes records in configurable chunks', function () {
    for ($i = 1; $i <= 5; $i++) {
        CampaignEmailClickRawData::create([
            'workspace_id' => $this->workspace->id,
            'integration_id' => $this->integration->id,
            'external_campaign_id' => "campaign-chunk-{$i}",
            'subscriber_email_hash' => str_pad((string) $i, 64, '0', STR_PAD_LEFT),
            'raw_data' => [
                'clicked_at' => '2026-03-01',
            ],
        ]);
    }

    $this->transformer->setChunkSize(2);
    $result = $this->transformer->transform($this->batch);

    expect($result->created)->toBe(5);
    expect(CampaignEmailClick::count())->toBe(5);
    expect(ArchiveCampaignEmailClick::count())->toBe(5);
});

// ── Supports Method ──────────────────────────────────────────────────

it('supports campaign_email_clicks data type', function () {
    expect($this->transformer->supports('campaign_email_clicks'))->toBeTrue();
    expect($this->transformer->supports('campaign_emails'))->toBeFalse();
    expect($this->transformer->supports('conversion_sales'))->toBeFalse();
});
