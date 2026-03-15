<?php

use App\Models\Archives\ArchiveConversionSale;
use App\Models\ConversionSale;
use App\Models\ConversionSaleRawData;
use App\Models\ExtractionBatch;
use App\Models\IdentityHash;
use App\Models\Integration;
use App\Models\Organization;
use App\Models\Workspace;
use App\Services\Transformation\ChangeDetector;
use App\Services\Transformation\ConversionSaleTransformer;
use App\Services\Transformation\IdentityHashingService;

beforeEach(function () {
    $this->org = Organization::create(['name' => 'Test Org']);
    $this->workspace = new Workspace(['name' => 'Default']);
    $this->workspace->organization_id = $this->org->id;
    $this->workspace->is_default = true;
    $this->workspace->save();

    $this->integration = new Integration([
        'name' => 'Test Integration',
        'platform' => 'voluum',
        'data_types' => ['conversion_sales'],
        'is_active' => true,
        'sync_interval_minutes' => 60,
    ]);
    $this->integration->workspace_id = $this->workspace->id;
    $this->integration->organization_id = $this->org->id;
    $this->integration->save();

    $this->batch = ExtractionBatch::create([
        'integration_id' => $this->integration->id,
        'workspace_id' => $this->workspace->id,
        'data_type' => 'conversion_sales',
        'status' => ExtractionBatch::STATUS_EXTRACTED,
    ]);

    $this->transformer = new ConversionSaleTransformer(new IdentityHashingService, new ChangeDetector);
});

// ── Voluum Transformation ─────────────────────────────────────────────

it('transforms Voluum raw data to normalized fact record', function () {
    ConversionSaleRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'vol-123',
        'raw_data' => [
            'conversionId' => 'vol-123',
            'revenue' => 49.99,
            'cost' => 10.00,
            'payout' => 25.00,
            'conversionTimestamp' => '2026-02-15T14:30:00Z',
            'email' => 'buyer@example.com',
        ],
    ]);

    $result = $this->transformer->transform($this->batch);

    expect($result->created)->toBe(1);
    expect($result->errors)->toBeEmpty();

    $sale = ConversionSale::first();
    expect($sale->external_id)->toBe('vol-123');
    expect((float) $sale->revenue)->toBe(49.99);
    expect((float) $sale->cost)->toBe(10.00);
    expect((float) $sale->payout)->toBe(25.00);
    expect($sale->converted_at)->not->toBeNull();
    expect($sale->converted_at->year)->toBe(2026);
    expect($sale->identity_hash_id)->not->toBeNull();
    expect($sale->extraction_batch_id)->toBe($this->batch->id);
    expect($sale->transformed_at)->not->toBeNull();
    expect($sale->raw_data_id)->not->toBeNull();
    expect($sale->integration_id)->toBe($this->integration->id);
});

// ── Identity Hash Resolution ──────────────────────────────────────────

it('resolves identity_hash_id from subscriber email in payload', function () {
    ConversionSaleRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'vol-hash',
        'raw_data' => [
            'conversionId' => 'vol-hash',
            'revenue' => 100,
            'email' => 'subscriber@example.com',
        ],
    ]);

    $this->transformer->transform($this->batch);

    $sale = ConversionSale::first();
    expect($sale->identity_hash_id)->not->toBeNull();

    $identityHash = IdentityHash::find($sale->identity_hash_id);
    expect($identityHash)->not->toBeNull();
    expect($identityHash->type)->toBe('email');
});

// ── NULL Identity Hash ────────────────────────────────────────────────

it('sets identity_hash_id to NULL when no email available', function () {
    ConversionSaleRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'vol-no-email',
        'raw_data' => [
            'conversionId' => 'vol-no-email',
            'revenue' => 100,
            // No email field
        ],
    ]);

    $this->transformer->transform($this->batch);

    $sale = ConversionSale::first();
    expect($sale->identity_hash_id)->toBeNull();
});

// ── Monetary Field Normalization ──────────────────────────────────────

it('normalizes monetary fields from strings to decimals', function () {
    ConversionSaleRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'vol-money',
        'raw_data' => [
            'conversionId' => 'vol-money',
            'revenue' => '$49.99',
            'cost' => '10.50',
            'payout' => 25,
        ],
    ]);

    $this->transformer->transform($this->batch);

    $sale = ConversionSale::first();
    expect((float) $sale->revenue)->toBe(49.99);
    expect((float) $sale->cost)->toBe(10.50);
    expect((float) $sale->payout)->toBe(25.00);
});

// ── NULL Monetary Fields ──────────────────────────────────────────────

it('preserves NULL for missing monetary fields', function () {
    ConversionSaleRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'vol-sparse',
        'raw_data' => [
            'conversionId' => 'vol-sparse',
            'revenue' => 100,
            // No cost or payout
        ],
    ]);

    $this->transformer->transform($this->batch);

    $sale = ConversionSale::first();
    expect((float) $sale->revenue)->toBe(100.00);
    expect($sale->payout)->toBeNull();
    expect($sale->cost)->toBeNull();
});

// ── Upsert Behavior ──────────────────────────────────────────────────

it('updates existing sale on re-transformation without duplicating', function () {
    $rawData = ConversionSaleRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'vol-upsert',
        'raw_data' => [
            'conversionId' => 'vol-upsert',
            'revenue' => 100,
        ],
    ]);

    // First transformation
    $result1 = $this->transformer->transform($this->batch);
    expect($result1->created)->toBe(1);
    expect(ConversionSale::count())->toBe(1);
    expect((float) ConversionSale::first()->revenue)->toBe(100.00);

    // Update raw data and re-transform
    $rawData->update([
        'raw_data' => [
            'conversionId' => 'vol-upsert',
            'revenue' => 200,
        ],
    ]);

    $result2 = $this->transformer->transform($this->batch);
    expect($result2->updated)->toBe(1);
    expect($result2->created)->toBe(0);
    expect(ConversionSale::count())->toBe(1);
    expect((float) ConversionSale::first()->revenue)->toBe(200.00);
});

// ── Archive Creation ──────────────────────────────────────────────────

it('creates archive snapshot during transformation', function () {
    ConversionSaleRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'vol-archive',
        'raw_data' => [
            'conversionId' => 'vol-archive',
            'revenue' => 50,
            'extra_field' => 'preserved',
        ],
    ]);

    $this->transformer->transform($this->batch);

    expect(ArchiveConversionSale::count())->toBe(1);

    $archive = ArchiveConversionSale::first();
    expect($archive->workspace_id)->toBe($this->workspace->id);
    expect($archive->extraction_batch_id)->toBe($this->batch->id);
    expect($archive->payload['extra_field'])->toBe('preserved');
});

// ── Supports Method ──────────────────────────────────────────────────

it('supports conversion_sales data type', function () {
    expect($this->transformer->supports('conversion_sales'))->toBeTrue();
    expect($this->transformer->supports('campaign_emails'))->toBeFalse();
    expect($this->transformer->supports('campaign_email_clicks'))->toBeFalse();
});
