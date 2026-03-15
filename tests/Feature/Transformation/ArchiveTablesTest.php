<?php

use App\Models\Archives\ArchiveCampaignEmail;
use App\Models\Archives\ArchiveCampaignEmailClick;
use App\Models\Archives\ArchiveConversionSale;
use App\Models\CampaignEmailClickRawData;
use App\Models\CampaignEmailRawData;
use App\Models\ConversionSaleRawData;
use App\Models\Integration;
use App\Models\Organization;
use App\Models\Workspace;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->org = Organization::create(['name' => 'Test Org']);
    $this->workspace = new Workspace(['name' => 'Default']);
    $this->workspace->organization_id = $this->org->id;
    $this->workspace->is_default = true;
    $this->workspace->save();

    $this->integration = new Integration([
        'name' => 'Test',
        'platform' => 'activecampaign',
        'is_active' => true,
        'data_types' => ['campaign_emails'],
        'sync_interval_minutes' => 60,
    ]);
    $this->integration->workspace_id = $this->workspace->id;
    $this->integration->organization_id = $this->org->id;
    $this->integration->save();
});

// ── Migration Tests ───────────────────────────────────────────────────

it('creates archives_campaign_emails table with correct columns', function () {
    $columns = Schema::getColumnListing('archives_campaign_emails');

    expect($columns)->toContain('id');
    expect($columns)->toContain('workspace_id');
    expect($columns)->toContain('raw_data_id');
    expect($columns)->toContain('extraction_batch_id');
    expect($columns)->toContain('payload');
    expect($columns)->toContain('archived_at');
});

it('creates archives_campaign_email_clicks table with correct columns', function () {
    $columns = Schema::getColumnListing('archives_campaign_email_clicks');

    expect($columns)->toContain('id');
    expect($columns)->toContain('workspace_id');
    expect($columns)->toContain('raw_data_id');
    expect($columns)->toContain('extraction_batch_id');
    expect($columns)->toContain('payload');
    expect($columns)->toContain('archived_at');
});

it('creates archives_conversion_sales table with correct columns', function () {
    $columns = Schema::getColumnListing('archives_conversion_sales');

    expect($columns)->toContain('id');
    expect($columns)->toContain('workspace_id');
    expect($columns)->toContain('raw_data_id');
    expect($columns)->toContain('extraction_batch_id');
    expect($columns)->toContain('payload');
    expect($columns)->toContain('archived_at');
});

// ── Cascade Delete on Workspace ───────────────────────────────────────

it('cascades delete to archive records when workspace is deleted', function () {
    $rawData = CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'ext-001',
        'raw_data' => ['name' => 'Test'],
    ]);

    ArchiveCampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'raw_data_id' => $rawData->id,
        'extraction_batch_id' => 1,
        'payload' => ['name' => 'Test'],
    ]);

    expect(ArchiveCampaignEmail::count())->toBe(1);

    $this->workspace->forceDelete();

    expect(ArchiveCampaignEmail::count())->toBe(0);
});

// ── Cascade Delete on Raw Data ────────────────────────────────────────

it('cascades delete to archive when raw data record is deleted', function () {
    $rawData = CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'ext-002',
        'raw_data' => ['name' => 'Test 2'],
    ]);

    ArchiveCampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'raw_data_id' => $rawData->id,
        'extraction_batch_id' => 1,
        'payload' => ['name' => 'Test 2'],
    ]);

    expect(ArchiveCampaignEmail::count())->toBe(1);

    $rawData->delete();

    expect(ArchiveCampaignEmail::count())->toBe(0);
});

// ── Payload Cast ──────────────────────────────────────────────────────

it('casts payload to array on ArchiveCampaignEmail', function () {
    $rawData = CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'ext-003',
        'raw_data' => ['name' => 'Test'],
    ]);

    $archive = ArchiveCampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'raw_data_id' => $rawData->id,
        'extraction_batch_id' => 1,
        'payload' => ['key' => 'value', 'nested' => ['a' => 1]],
    ]);

    $fresh = ArchiveCampaignEmail::find($archive->id);
    expect($fresh->payload)->toBeArray();
    expect($fresh->payload['key'])->toBe('value');
    expect($fresh->payload['nested']['a'])->toBe(1);
});

it('casts payload to array on ArchiveCampaignEmailClick', function () {
    $rawData = CampaignEmailClickRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_campaign_id' => 'camp-001',
        'subscriber_email_hash' => str_repeat('a', 64),
        'raw_data' => ['url' => 'test'],
    ]);

    $archive = ArchiveCampaignEmailClick::create([
        'workspace_id' => $this->workspace->id,
        'raw_data_id' => $rawData->id,
        'extraction_batch_id' => 1,
        'payload' => ['url' => 'https://example.com'],
    ]);

    $fresh = ArchiveCampaignEmailClick::find($archive->id);
    expect($fresh->payload)->toBeArray();
    expect($fresh->payload['url'])->toBe('https://example.com');
});

it('casts payload to array on ArchiveConversionSale', function () {
    $rawData = ConversionSaleRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'conv-001',
        'raw_data' => ['revenue' => 99.99],
    ]);

    $archive = ArchiveConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'raw_data_id' => $rawData->id,
        'extraction_batch_id' => 1,
        'payload' => ['revenue' => 99.99],
    ]);

    $fresh = ArchiveConversionSale::find($archive->id);
    expect($fresh->payload)->toBeArray();
    expect($fresh->payload['revenue'])->toBe(99.99);
});
