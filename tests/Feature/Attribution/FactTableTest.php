<?php

use App\Models\CampaignEmail;
use App\Models\CampaignEmailClick;
use App\Models\ConversionSale;
use App\Models\Organization;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->org = Organization::create(['name' => 'Test Org']);
    $this->workspace = $this->org->workspaces()->create(['name' => 'Test WS']);
});

// ── CampaignEmail Tests ─────────────────────────────────────────────────

it('creates campaign email with correct attributes', function () {
    $email = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'external_id' => 'ext-001',
        'name' => 'March Newsletter',
        'subject' => 'Check out our latest updates',
        'from_name' => 'Marketing',
        'from_email' => 'marketing@example.com',
        'type' => 'newsletter',
        'sent' => 1000,
        'delivered' => 980,
        'bounced' => 20,
        'opens' => 450,
        'unique_opens' => 400,
        'clicks' => 120,
        'unique_clicks' => 100,
        'platform_revenue' => 5432.10,
        'sent_at' => '2026-03-14 10:00:00',
    ]);

    expect($email->workspace->id)->toBe($this->workspace->id);
    expect($email->sent)->toBe(1000);
    expect($email->platform_revenue)->toBe('5432.10');
    expect($email->sent_at)->toBeInstanceOf(Carbon::class);
});

it('campaign email has clicks relationship', function () {
    $email = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'external_id' => 'ext-002',
    ]);

    CampaignEmailClick::create([
        'workspace_id' => $this->workspace->id,
        'campaign_email_id' => $email->id,
        'clicked_at' => now(),
    ]);

    expect($email->emailClicks)->toHaveCount(1);
});

// ── CampaignEmailClick Tests ────────────────────────────────────────────

it('creates campaign email click with relationships', function () {
    $email = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'external_id' => 'ext-003',
    ]);

    $click = CampaignEmailClick::create([
        'workspace_id' => $this->workspace->id,
        'campaign_email_id' => $email->id,
        'clicked_at' => '2026-03-14 11:30:00',
    ]);

    expect($click->workspace->id)->toBe($this->workspace->id);
    expect($click->campaignEmail->id)->toBe($email->id);
    expect($click->clicked_at)->toBeInstanceOf(Carbon::class);
});

it('campaign email click identity_hash_id is nullable', function () {
    $click = CampaignEmailClick::create([
        'workspace_id' => $this->workspace->id,
        'identity_hash_id' => null,
        'clicked_at' => now(),
    ]);

    expect($click->identity_hash_id)->toBeNull();
});

// ── ConversionSale Tests ────────────────────────────────────────────────

it('creates conversion sale with correct attributes', function () {
    $sale = ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'external_id' => 'sale-001',
        'revenue' => 150.50,
        'payout' => 75.25,
        'cost' => 10.00,
        'converted_at' => '2026-03-14 14:00:00',
    ]);

    expect($sale->workspace->id)->toBe($this->workspace->id);
    expect($sale->revenue)->toBe('150.50');
    expect($sale->payout)->toBe('75.25');
    expect($sale->cost)->toBe('10.00');
    expect($sale->converted_at)->toBeInstanceOf(Carbon::class);
});

// ── Nullable Metric Tests ───────────────────────────────────────────────

it('preserves null vs zero distinction for metric columns', function () {
    $emailWithNull = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'external_id' => 'null-test',
        'sent' => null,
        'opens' => null,
    ]);

    $emailWithZero = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'external_id' => 'zero-test',
        'sent' => 0,
        'opens' => 0,
    ]);

    $reloadedNull = CampaignEmail::find($emailWithNull->id);
    $reloadedZero = CampaignEmail::find($emailWithZero->id);

    expect($reloadedNull->sent)->toBeNull();
    expect($reloadedNull->opens)->toBeNull();
    expect($reloadedZero->sent)->toBe(0);
    expect($reloadedZero->opens)->toBe(0);
});

// ── Decimal Precision Tests ─────────────────────────────────────────────

it('casts decimal columns with correct precision', function () {
    $sale = ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'external_id' => 'decimal-test',
        'revenue' => 1234.56,
        'payout' => 0.01,
        'cost' => 999999999.99,
    ]);

    $reloaded = ConversionSale::find($sale->id);

    expect($reloaded->revenue)->toBe('1234.56');
    expect($reloaded->payout)->toBe('0.01');
    expect($reloaded->cost)->toBe('999999999.99');
});

// ── Soft Delete Tests ───────────────────────────────────────────────────

it('supports soft deletes on all fact tables', function () {
    $email = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'external_id' => 'soft-del-1',
    ]);

    $click = CampaignEmailClick::create([
        'workspace_id' => $this->workspace->id,
        'campaign_email_id' => $email->id,
        'clicked_at' => now(),
    ]);

    $sale = ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'external_id' => 'soft-del-sale',
    ]);

    $email->delete();
    $click->delete();
    $sale->delete();

    expect(CampaignEmail::find($email->id))->toBeNull();
    expect(CampaignEmailClick::find($click->id))->toBeNull();
    expect(ConversionSale::find($sale->id))->toBeNull();

    expect(CampaignEmail::withTrashed()->find($email->id))->not->toBeNull();
    expect(CampaignEmailClick::withTrashed()->find($click->id))->not->toBeNull();
    expect(ConversionSale::withTrashed()->find($sale->id))->not->toBeNull();
});

// ── Fillable Tests ──────────────────────────────────────────────────────

it('has correct fillable attributes on CampaignEmail', function () {
    $model = new CampaignEmail;
    $fillable = $model->getFillable();

    expect($fillable)->toContain('workspace_id', 'raw_data_id', 'integration_id', 'external_id');
    expect($fillable)->toContain('name', 'subject', 'from_name', 'from_email', 'type');
    expect($fillable)->toContain('sent', 'delivered', 'bounced', 'opens', 'clicks');
    expect($fillable)->toContain('platform_revenue', 'sent_at');
});

it('has correct fillable attributes on CampaignEmailClick', function () {
    $model = new CampaignEmailClick;
    $fillable = $model->getFillable();

    expect($fillable)->toContain('workspace_id', 'raw_data_id', 'integration_id');
    expect($fillable)->toContain('campaign_email_id', 'identity_hash_id', 'clicked_at');
});

it('has correct fillable attributes on ConversionSale', function () {
    $model = new ConversionSale;
    $fillable = $model->getFillable();

    expect($fillable)->toContain('workspace_id', 'raw_data_id', 'integration_id');
    expect($fillable)->toContain('external_id', 'identity_hash_id', 'revenue', 'payout', 'cost', 'converted_at');
});
