<?php

use App\Models\Archives\ArchiveCampaignEmail;
use App\Models\CampaignEmail;
use App\Models\CampaignEmailRawData;
use App\Models\ExtractionBatch;
use App\Models\Integration;
use App\Models\Organization;
use App\Models\Workspace;
use App\Services\Transformation\CampaignEmailTransformer;
use App\Services\Transformation\ChangeDetector;

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

    $this->transformer = new CampaignEmailTransformer(new ChangeDetector);
});

// ── ActiveCampaign Transformation ─────────────────────────────────────

it('transforms ActiveCampaign raw data to normalized fact record', function () {
    // Raw data uses the same keys the ActiveCampaign connector outputs
    CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'ac-123',
        'raw_data' => [
            'external_id' => 'ac-123',
            'name' => 'Welcome Email',
            'subject' => 'Welcome aboard!',
            'from_name' => 'Support',
            'from_email' => 'support@example.com',
            'type' => 'single',
            'sent' => 1000,
            'delivered' => 950,
            'bounces' => 50,
            'complaints' => 2,
            'unsubscribes' => 5,
            'opens' => 300,
            'unique_opens' => 250,
            'clicks' => 100,
            'unique_clicks' => 80,
            'platform_revenue' => 500.50,
            'sent_at' => '2026-01-15T10:00:00Z',
        ],
    ]);

    $result = $this->transformer->transform($this->batch);

    expect($result->created)->toBe(1);
    expect($result->updated)->toBe(0);
    expect($result->errors)->toBeEmpty();

    $email = CampaignEmail::first();
    expect($email->external_id)->toBe('ac-123');
    expect($email->name)->toBe('Welcome Email');
    expect($email->subject)->toBe('Welcome aboard!');
    expect($email->from_name)->toBe('Support');
    expect($email->from_email)->toBe('support@example.com');
    expect($email->type)->toBe('single');
    expect($email->sent)->toBe(1000);
    expect($email->delivered)->toBe(950);
    expect($email->bounced)->toBe(50);
    expect($email->complaints)->toBe(2);
    expect($email->unsubscribes)->toBe(5);
    expect($email->opens)->toBe(300);
    expect($email->unique_opens)->toBe(250);
    expect($email->clicks)->toBe(100);
    expect($email->unique_clicks)->toBe(80);
    expect((float) $email->platform_revenue)->toBe(500.50);
    expect($email->sent_at)->not->toBeNull();
    expect($email->extraction_batch_id)->toBe($this->batch->id);
    expect($email->transformed_at)->not->toBeNull();
    expect($email->raw_data_id)->not->toBeNull();
    expect($email->integration_id)->toBe($this->integration->id);
});

// ── ExpertSender Transformation ───────────────────────────────────────

it('transforms ExpertSender raw data using connector-normalized fields', function () {
    $this->integration->update(['platform' => 'expertsender']);

    // Raw data uses the same keys the ExpertSender connector outputs
    // (connector normalizes PascalCase API fields to snake_case)
    CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'es-456',
        'raw_data' => [
            'external_id' => 'es-456',
            'name' => 'Newsletter',
            'subject' => 'Monthly Update',
            'from_name' => 'Team',
            'from_email' => 'team@example.com',
            'type' => 'newsletter',
            'sent' => 2000,
            'delivered' => 1900,
            'bounces' => 100,
            'complaints' => 3,
            'unsubscribes' => 10,
            'opens' => 500,
            'unique_opens' => 400,
            'clicks' => 200,
            'unique_clicks' => 150,
            'platform_revenue' => 1200.00,
            'sent_at' => '2026-02-01 08:00:00',
        ],
    ]);

    $result = $this->transformer->transform($this->batch);

    expect($result->created)->toBe(1);

    $email = CampaignEmail::first();
    expect($email->external_id)->toBe('es-456');
    expect($email->name)->toBe('Newsletter');
    expect($email->subject)->toBe('Monthly Update');
    expect($email->from_name)->toBe('Team');
    expect($email->sent)->toBe(2000);
    expect($email->delivered)->toBe(1900);
    expect($email->clicks)->toBe(200);
    expect($email->unique_clicks)->toBe(150);
});

// ── Unknown Platform ─────────────────────────────────────────────────

it('throws when no field map exists for a platform', function () {
    $this->integration->update(['platform' => 'unknown_platform']);

    CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'unk-1',
        'raw_data' => [
            'external_id' => 'unk-1',
            'name' => 'Test',
        ],
    ]);

    $result = $this->transformer->transform($this->batch);

    expect($result->errors)->not->toBeEmpty();
    expect($result->errors[0]['error'])->toContain('No CampaignEmail field map defined for platform');
});

// ── Upsert Behavior ──────────────────────────────────────────────────

it('updates existing fact record on re-transformation without duplicating', function () {
    $rawData = CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'ac-789',
        'raw_data' => [
            'external_id' => 'ac-789',
            'name' => 'Original Name',
            'subject' => 'Original Subject',
            'sent' => 100,
            'sent_at' => '2026-01-01',
        ],
    ]);

    // First transformation
    $result1 = $this->transformer->transform($this->batch);
    expect($result1->created)->toBe(1);
    expect(CampaignEmail::count())->toBe(1);
    expect(CampaignEmail::first()->name)->toBe('Original Name');

    // Update raw data and re-transform
    $rawData->update([
        'raw_data' => [
            'external_id' => 'ac-789',
            'name' => 'Updated Name',
            'subject' => 'Updated Subject',
            'sent' => 200,
            'sent_at' => '2026-01-01',
        ],
    ]);

    $result2 = $this->transformer->transform($this->batch);
    expect($result2->updated)->toBe(1);
    expect($result2->created)->toBe(0);
    expect(CampaignEmail::count())->toBe(1);
    expect(CampaignEmail::first()->name)->toBe('Updated Name');
    expect(CampaignEmail::first()->sent)->toBe(200);
});

// ── NULL Handling ─────────────────────────────────────────────────────

it('preserves NULL for missing metric fields', function () {
    CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'ac-null',
        'raw_data' => [
            'external_id' => 'ac-null',
            'name' => 'Sparse Email',
            'sent' => 500,
            // No delivered, bounced, complaints, etc.
        ],
    ]);

    $result = $this->transformer->transform($this->batch);

    expect($result->created)->toBe(1);

    $email = CampaignEmail::first();
    expect($email->sent)->toBe(500);
    expect($email->delivered)->toBeNull();
    expect($email->bounced)->toBeNull();
    expect($email->complaints)->toBeNull();
    expect($email->unsubscribes)->toBeNull();
    expect($email->opens)->toBeNull();
    expect($email->unique_opens)->toBeNull();
    expect($email->clicks)->toBeNull();
    expect($email->unique_clicks)->toBeNull();
    expect($email->platform_revenue)->toBeNull();
});

// ── Archive Creation ──────────────────────────────────────────────────

it('creates archive snapshot during transformation', function () {
    CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'ac-archive',
        'raw_data' => [
            'external_id' => 'ac-archive',
            'name' => 'Archive Test',
            'sent' => 100,
        ],
    ]);

    $this->transformer->transform($this->batch);

    expect(ArchiveCampaignEmail::count())->toBe(1);

    $archive = ArchiveCampaignEmail::first();
    expect($archive->workspace_id)->toBe($this->workspace->id);
    expect($archive->extraction_batch_id)->toBe($this->batch->id);
    expect($archive->payload)->toBeArray();
    expect($archive->payload['name'])->toBe('Archive Test');
});

// ── Chunked Processing ───────────────────────────────────────────────

it('processes records in configurable chunks', function () {
    // Create 5 records and set chunk size to 2
    for ($i = 1; $i <= 5; $i++) {
        CampaignEmailRawData::create([
            'workspace_id' => $this->workspace->id,
            'integration_id' => $this->integration->id,
            'external_id' => "chunk-{$i}",
            'raw_data' => [
                'external_id' => "chunk-{$i}",
                'name' => "Email {$i}",
                'sent' => $i * 100,
            ],
        ]);
    }

    $this->transformer->setChunkSize(2);
    $result = $this->transformer->transform($this->batch);

    expect($result->created)->toBe(5);
    expect(CampaignEmail::count())->toBe(5);
    expect(ArchiveCampaignEmail::count())->toBe(5);
});

// ── Supports Method ──────────────────────────────────────────────────

it('supports campaign_emails data type', function () {
    expect($this->transformer->supports('campaign_emails'))->toBeTrue();
    expect($this->transformer->supports('campaign_email_clicks'))->toBeFalse();
    expect($this->transformer->supports('conversion_sales'))->toBeFalse();
});

// ── Timestamp Parsing ─────────────────────────────────────────────────

it('handles various timestamp formats', function () {
    // ISO 8601
    CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'ts-iso',
        'raw_data' => [
            'external_id' => 'ts-iso',
            'name' => 'ISO Date',
            'sent_at' => '2026-03-01T12:00:00Z',
        ],
    ]);

    $this->transformer->transform($this->batch);

    $email = CampaignEmail::where('external_id', 'ts-iso')->first();
    expect($email->sent_at)->not->toBeNull();
    expect($email->sent_at->year)->toBe(2026);
    expect($email->sent_at->month)->toBe(3);
});
