<?php

use App\Models\CampaignEmail;
use App\Models\CampaignEmailClick;
use App\Models\ConversionSale;
use App\Models\Integration;
use App\Models\Organization;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
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

// ── Column Existence ─────────────────────────────────────────────────

it('adds extraction_batch_id and transformed_at to campaign_emails', function () {
    $columns = Schema::getColumnListing('campaign_emails');

    expect($columns)->toContain('extraction_batch_id');
    expect($columns)->toContain('transformed_at');
});

it('adds extraction_batch_id and transformed_at to campaign_email_clicks', function () {
    $columns = Schema::getColumnListing('campaign_email_clicks');

    expect($columns)->toContain('extraction_batch_id');
    expect($columns)->toContain('transformed_at');
});

it('adds extraction_batch_id and transformed_at to conversion_sales', function () {
    $columns = Schema::getColumnListing('conversion_sales');

    expect($columns)->toContain('extraction_batch_id');
    expect($columns)->toContain('transformed_at');
});

// ── Nullable Columns ─────────────────────────────────────────────────

it('allows null extraction_batch_id and transformed_at on campaign_emails', function () {
    $record = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'ext-001',
        'name' => 'Test Campaign',
    ]);

    $fresh = CampaignEmail::find($record->id);
    expect($fresh->extraction_batch_id)->toBeNull();
    expect($fresh->transformed_at)->toBeNull();
});

it('allows null extraction_batch_id and transformed_at on campaign_email_clicks', function () {
    $email = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'ext-001',
        'name' => 'Test Campaign',
    ]);

    $record = CampaignEmailClick::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'campaign_email_id' => $email->id,
        'clicked_at' => now(),
    ]);

    $fresh = CampaignEmailClick::find($record->id);
    expect($fresh->extraction_batch_id)->toBeNull();
    expect($fresh->transformed_at)->toBeNull();
});

it('allows null extraction_batch_id and transformed_at on conversion_sales', function () {
    $record = ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'conv-001',
    ]);

    $fresh = ConversionSale::find($record->id);
    expect($fresh->extraction_batch_id)->toBeNull();
    expect($fresh->transformed_at)->toBeNull();
});

// ── Model Casts ──────────────────────────────────────────────────────

it('casts transformed_at to datetime on CampaignEmail', function () {
    $record = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'ext-cast',
        'name' => 'Cast Test',
        'extraction_batch_id' => 42,
        'transformed_at' => '2026-03-14 12:00:00',
    ]);

    $fresh = CampaignEmail::find($record->id);
    expect($fresh->transformed_at)->toBeInstanceOf(Carbon::class);
    expect($fresh->extraction_batch_id)->toBe(42);
});

it('casts transformed_at to datetime on CampaignEmailClick', function () {
    $email = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'ext-cast2',
        'name' => 'Cast Test',
    ]);

    $record = CampaignEmailClick::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'campaign_email_id' => $email->id,
        'clicked_at' => now(),
        'extraction_batch_id' => 99,
        'transformed_at' => '2026-03-14 12:00:00',
    ]);

    $fresh = CampaignEmailClick::find($record->id);
    expect($fresh->transformed_at)->toBeInstanceOf(Carbon::class);
    expect($fresh->extraction_batch_id)->toBe(99);
});

it('casts transformed_at to datetime on ConversionSale', function () {
    $record = ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'conv-cast',
        'extraction_batch_id' => 7,
        'transformed_at' => '2026-03-14 12:00:00',
    ]);

    $fresh = ConversionSale::find($record->id);
    expect($fresh->transformed_at)->toBeInstanceOf(Carbon::class);
    expect($fresh->extraction_batch_id)->toBe(7);
});

// ── Rollback ─────────────────────────────────────────────────────────

it('can rollback the ETL column migrations cleanly', function () {
    // Verify columns exist
    expect(Schema::hasColumn('campaign_emails', 'extraction_batch_id'))->toBeTrue();
    expect(Schema::hasColumn('campaign_emails', 'transformed_at'))->toBeTrue();

    // Rollback the ETL column migrations (1 last_summarized_at + 6 summary tables + 2 incremental processing + 3 ETL columns)
    Artisan::call('migrate:rollback', ['--step' => 12]);

    expect(Schema::hasColumn('campaign_emails', 'extraction_batch_id'))->toBeFalse();
    expect(Schema::hasColumn('campaign_emails', 'transformed_at'))->toBeFalse();
    expect(Schema::hasColumn('campaign_email_clicks', 'extraction_batch_id'))->toBeFalse();
    expect(Schema::hasColumn('campaign_email_clicks', 'transformed_at'))->toBeFalse();
    expect(Schema::hasColumn('conversion_sales', 'extraction_batch_id'))->toBeFalse();
    expect(Schema::hasColumn('conversion_sales', 'transformed_at'))->toBeFalse();

    // Re-migrate
    Artisan::call('migrate');

    expect(Schema::hasColumn('campaign_emails', 'extraction_batch_id'))->toBeTrue();
    expect(Schema::hasColumn('campaign_emails', 'transformed_at'))->toBeTrue();
});
