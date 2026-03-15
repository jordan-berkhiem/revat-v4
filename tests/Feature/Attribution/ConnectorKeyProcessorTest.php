<?php

use App\Models\AttributionConnector;
use App\Models\AttributionKey;
use App\Models\AttributionRecordKey;
use App\Models\CampaignEmail;
use App\Models\CampaignEmailClick;
use App\Models\ConversionSale;
use App\Models\Effort;
use App\Models\Initiative;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Workspace;
use App\Services\ConnectorKeyProcessor;

beforeEach(function () {
    $this->org = Organization::create(['name' => 'Test Org']);
    $this->workspace = new Workspace(['name' => 'Default']);
    $this->workspace->organization_id = $this->org->id;
    $this->workspace->is_default = true;
    $this->workspace->save();

    // PIE hierarchy
    $program = Program::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Test Program',
        'code' => 'TP',
    ]);
    $initiative = Initiative::create([
        'workspace_id' => $this->workspace->id,
        'program_id' => $program->id,
        'name' => 'Test Initiative',
        'code' => 'TI',
    ]);
    $this->effort = Effort::create([
        'workspace_id' => $this->workspace->id,
        'initiative_id' => $initiative->id,
        'name' => 'Test Effort',
        'code' => 'TE',
        'channel_type' => 'email',
        'status' => 'active',
    ]);

    $this->connector = AttributionConnector::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Email Connector',
        'campaign_integration_id' => 1,
        'campaign_data_type' => 'email',
        'conversion_integration_id' => 2,
        'conversion_data_type' => 'sale',
        'field_mappings' => [['campaign' => 'from_email', 'conversion' => 'external_id']],
    ]);

    $this->processor = app(ConnectorKeyProcessor::class);
});

it('computes correct SHA-256 key hashes from field values', function () {
    $email = 'test@example.com';

    CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'effort_id' => $this->effort->id,
        'external_id' => 'camp-1',
        'from_email' => $email,
    ]);

    $this->processor->processKeys($this->connector);

    $key = AttributionKey::where('connector_id', $this->connector->id)->first();
    expect($key)->not->toBeNull();
    expect($key->key_value)->toBe($email);

    // Verify hash is correct SHA-256 (hex representation via BinaryHash cast)
    $expectedHash = hash('sha256', $email);
    expect($key->key_hash)->toBe($expectedHash);
});

it('links campaign records to correct keys via attribution_record_keys', function () {
    $email1 = 'alice@example.com';
    $email2 = 'bob@example.com';

    $camp1 = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'effort_id' => $this->effort->id,
        'external_id' => 'camp-1',
        'from_email' => $email1,
    ]);
    $camp2 = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'effort_id' => $this->effort->id,
        'external_id' => 'camp-2',
        'from_email' => $email2,
    ]);

    $this->processor->processKeys($this->connector);

    // Check record keys were created
    $recordKey1 = AttributionRecordKey::where('connector_id', $this->connector->id)
        ->where('record_type', 'campaign_email')
        ->where('record_id', $camp1->id)
        ->first();
    expect($recordKey1)->not->toBeNull();

    $recordKey2 = AttributionRecordKey::where('connector_id', $this->connector->id)
        ->where('record_type', 'campaign_email')
        ->where('record_id', $camp2->id)
        ->first();
    expect($recordKey2)->not->toBeNull();

    // Different keys for different emails
    expect($recordKey1->attribution_key_id)->not->toBe($recordKey2->attribution_key_id);
});

it('links conversion records to correct keys via attribution_record_keys', function () {
    $email = 'shared@example.com';

    CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'effort_id' => $this->effort->id,
        'external_id' => 'camp-1',
        'from_email' => $email,
    ]);
    $conversion = ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'external_id' => $email, // matches campaign from_email
        'revenue' => 100,
        'converted_at' => now(),
    ]);

    $this->processor->processKeys($this->connector);

    // Both records should share the same key
    $campRecordKey = AttributionRecordKey::where('record_type', 'campaign_email')->first();
    $convRecordKey = AttributionRecordKey::where('record_type', 'conversion_sale')
        ->where('record_id', $conversion->id)
        ->first();

    expect($campRecordKey)->not->toBeNull();
    expect($convRecordKey)->not->toBeNull();
    expect($campRecordKey->attribution_key_id)->toBe($convRecordKey->attribution_key_id);
});

it('is idempotent: re-processing produces same keys without duplicates', function () {
    CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'effort_id' => $this->effort->id,
        'external_id' => 'camp-1',
        'from_email' => 'test@example.com',
    ]);
    ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'external_id' => 'test@example.com',
        'revenue' => 100,
        'converted_at' => now(),
    ]);

    $this->processor->processKeys($this->connector);
    $keysAfterFirst = AttributionKey::count();
    $recordKeysAfterFirst = AttributionRecordKey::count();

    // Process again
    $this->processor->processKeys($this->connector);
    $keysAfterSecond = AttributionKey::count();
    $recordKeysAfterSecond = AttributionRecordKey::count();

    expect($keysAfterSecond)->toBe($keysAfterFirst);
    expect($recordKeysAfterSecond)->toBe($recordKeysAfterFirst);
});

it('processes campaign email clicks and links them to keys', function () {
    $email = 'clicker@example.com';

    $campaign = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'effort_id' => $this->effort->id,
        'external_id' => 'camp-1',
        'from_email' => $email,
    ]);

    $click = CampaignEmailClick::create([
        'workspace_id' => $this->workspace->id,
        'campaign_email_id' => $campaign->id,
        'clicked_at' => now()->subDay(),
    ]);

    $this->processor->processKeys($this->connector);

    $clickRecordKey = AttributionRecordKey::where('record_type', 'campaign_email_click')
        ->where('record_id', $click->id)
        ->first();

    expect($clickRecordKey)->not->toBeNull();

    // Click should share the same key as its parent campaign
    $campRecordKey = AttributionRecordKey::where('record_type', 'campaign_email')
        ->where('record_id', $campaign->id)
        ->first();

    expect($clickRecordKey->attribution_key_id)->toBe($campRecordKey->attribution_key_id);
});
