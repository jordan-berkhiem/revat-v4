<?php

use App\Models\CampaignEmailClickRawData;
use App\Models\CampaignEmailRawData;
use App\Models\ConversionSaleRawData;
use App\Models\Organization;
use App\Models\Workspace;
use Illuminate\Database\QueryException;
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

it('creates campaign_email_raw_data table', function () {
    expect(Schema::hasTable('campaign_email_raw_data'))->toBeTrue()
        ->and(Schema::hasColumns('campaign_email_raw_data', [
            'id', 'workspace_id', 'integration_id', 'external_id', 'raw_data', 'created_at', 'updated_at',
        ]))->toBeTrue();
});

it('creates campaign_email_click_raw_data table', function () {
    expect(Schema::hasTable('campaign_email_click_raw_data'))->toBeTrue()
        ->and(Schema::hasColumns('campaign_email_click_raw_data', [
            'id', 'workspace_id', 'integration_id', 'external_campaign_id',
            'subscriber_email_hash', 'clicked_url', 'url_params', 'raw_data',
        ]))->toBeTrue();
});

it('creates conversion_sale_raw_data table', function () {
    expect(Schema::hasTable('conversion_sale_raw_data'))->toBeTrue()
        ->and(Schema::hasColumns('conversion_sale_raw_data', [
            'id', 'workspace_id', 'integration_id', 'external_id', 'raw_data',
        ]))->toBeTrue();
});

// ── CampaignEmailRawData Tests ────────────────────────────────────────

it('creates campaign email raw data with array cast', function () {
    $record = CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'camp-123',
        'raw_data' => ['subject' => 'Test', 'sent' => 100],
    ]);

    $record->refresh();
    expect($record->raw_data)->toBe(['subject' => 'Test', 'sent' => 100])
        ->and($record->external_id)->toBe('camp-123');
});

it('enforces unique constraint on campaign email raw data', function () {
    CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'dup-1',
        'raw_data' => ['test' => true],
    ]);

    expect(fn () => CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'dup-1',
        'raw_data' => ['test' => false],
    ]))->toThrow(QueryException::class);
});

it('has workspace and integration relationships on campaign email raw data', function () {
    $record = CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'rel-1',
        'raw_data' => [],
    ]);

    expect($record->workspace->id)->toBe($this->workspace->id)
        ->and($record->integration->id)->toBe($this->integration->id);
});

// ── CampaignEmailClickRawData Tests ───────────────────────────────────

it('creates campaign email click raw data with casts', function () {
    $record = CampaignEmailClickRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_campaign_id' => 'camp-456',
        'subscriber_email_hash' => hash('sha256', 'test@example.com'),
        'clicked_url' => 'https://example.com/offer',
        'url_params' => ['utm_source' => 'email'],
        'raw_data' => ['click_time' => '2024-01-01'],
    ]);

    $record->refresh();
    expect($record->url_params)->toBe(['utm_source' => 'email'])
        ->and($record->raw_data)->toBe(['click_time' => '2024-01-01']);
});

it('enforces named unique constraint on click raw data', function () {
    $hash = hash('sha256', 'test@example.com');

    CampaignEmailClickRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_campaign_id' => 'camp-x',
        'subscriber_email_hash' => $hash,
        'raw_data' => [],
    ]);

    expect(fn () => CampaignEmailClickRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_campaign_id' => 'camp-x',
        'subscriber_email_hash' => $hash,
        'raw_data' => [],
    ]))->toThrow(QueryException::class);
});

// ── ConversionSaleRawData Tests ───────────────────────────────────────

it('creates conversion sale raw data', function () {
    $record = ConversionSaleRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'sale-789',
        'raw_data' => ['amount' => 99.99, 'currency' => 'USD'],
    ]);

    $record->refresh();
    expect($record->raw_data)->toBe(['amount' => 99.99, 'currency' => 'USD']);
});

it('enforces unique constraint on conversion sale raw data', function () {
    ConversionSaleRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'dup-sale',
        'raw_data' => [],
    ]);

    expect(fn () => ConversionSaleRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'dup-sale',
        'raw_data' => [],
    ]))->toThrow(QueryException::class);
});

// ── Integration Relationships ─────────────────────────────────────────

it('integration has raw data relationships', function () {
    CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'r1',
        'raw_data' => [],
    ]);

    CampaignEmailClickRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_campaign_id' => 'c1',
        'subscriber_email_hash' => hash('sha256', 'a@b.com'),
        'raw_data' => [],
    ]);

    ConversionSaleRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 's1',
        'raw_data' => [],
    ]);

    expect($this->integration->campaignEmailRawData)->toHaveCount(1)
        ->and($this->integration->campaignEmailClickRawData)->toHaveCount(1)
        ->and($this->integration->conversionSaleRawData)->toHaveCount(1);
});

// ── Cascade Delete Tests ──────────────────────────────────────────────

it('cascades delete from integration to raw data', function () {
    CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'cascade-1',
        'raw_data' => [],
    ]);

    // Force delete integration (soft-delete won't trigger FK cascade)
    $this->integration->forceDelete();

    expect(CampaignEmailRawData::where('integration_id', $this->integration->id)->count())->toBe(0);
});
