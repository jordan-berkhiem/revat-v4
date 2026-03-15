<?php

use App\Models\AttributionConnector;
use App\Models\AttributionKey;
use App\Models\AttributionRecordKey;
use App\Models\CampaignEmail;
use App\Models\CampaignEmailClick;
use App\Models\CampaignEmailRawData;
use App\Models\ConversionSale;
use App\Models\ConversionSaleRawData;
use App\Models\Effort;
use App\Models\Initiative;
use App\Models\Integration;
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

    // Campaign integration (Maropost)
    $this->campaignIntegration = $this->workspace->integrations()->create([
        'name' => 'Test Maropost',
        'platform' => 'maropost',
        'data_types' => ['campaign_emails', 'campaign_email_clicks'],
        'is_active' => true,
    ]);
    $this->campaignIntegration->setCredentials(['account_id' => 'test', 'auth_token' => 'test']);

    // Conversion integration (Voluum)
    $this->conversionIntegration = $this->workspace->integrations()->create([
        'name' => 'Test Voluum',
        'platform' => 'voluum',
        'data_types' => ['conversion_sales'],
        'is_active' => true,
    ]);
    $this->conversionIntegration->setCredentials(['access_key_id' => 'test', 'access_key_secret' => 'test']);

    $this->connector = AttributionConnector::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Email Connector',
        'campaign_integration_id' => $this->campaignIntegration->id,
        'campaign_data_type' => 'campaign_emails',
        'conversion_integration_id' => $this->conversionIntegration->id,
        'conversion_data_type' => 'conversion_sales',
        'field_mappings' => [['campaign' => 'from_email', 'conversion' => 'campaignid']],
    ]);

    $this->processor = app(ConnectorKeyProcessor::class);
});

it('resolves campaign fields from raw_data JSON and creates correct key hashes', function () {
    $email = 'sender@example.com';

    $rawData = CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->campaignIntegration->id,
        'external_id' => 'camp-1',
        'raw_data' => ['from_email' => $email, 'name' => 'Test Campaign'],
    ]);

    CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'raw_data_id' => $rawData->id,
        'integration_id' => $this->campaignIntegration->id,
        'effort_id' => $this->effort->id,
        'external_id' => 'camp-1',
        'from_email' => $email,
    ]);

    // Seed Voluum -TS raw data so conversion side has data
    $convRawData = ConversionSaleRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->conversionIntegration->id,
        'external_id' => 'conv-1',
        'raw_data' => [
            'customVariable1' => $email,
            'customVariable1-TS' => 'campaignid',
        ],
    ]);

    ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'raw_data_id' => $convRawData->id,
        'integration_id' => $this->conversionIntegration->id,
        'external_id' => 'conv-1',
        'revenue' => 100,
        'converted_at' => now(),
    ]);

    $this->processor->processKeys($this->connector);

    $key = AttributionKey::where('connector_id', $this->connector->id)->first();
    expect($key)->not->toBeNull();
    expect($key->key_value)->toBe($email);

    $expectedHash = hash('sha256', $email);
    expect($key->key_hash)->toBe($expectedHash);
});

it('links campaign records to correct keys via raw_data resolution', function () {
    $email1 = 'alice@example.com';
    $email2 = 'bob@example.com';

    $raw1 = CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->campaignIntegration->id,
        'external_id' => 'camp-1',
        'raw_data' => ['from_email' => $email1, 'name' => 'Camp 1'],
    ]);
    $raw2 = CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->campaignIntegration->id,
        'external_id' => 'camp-2',
        'raw_data' => ['from_email' => $email2, 'name' => 'Camp 2'],
    ]);

    $camp1 = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'raw_data_id' => $raw1->id,
        'integration_id' => $this->campaignIntegration->id,
        'effort_id' => $this->effort->id,
        'external_id' => 'camp-1',
        'from_email' => $email1,
    ]);
    $camp2 = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'raw_data_id' => $raw2->id,
        'integration_id' => $this->campaignIntegration->id,
        'effort_id' => $this->effort->id,
        'external_id' => 'camp-2',
        'from_email' => $email2,
    ]);

    // Seed Voluum conversion raw data so 'campaignid' field validates
    $convRaw = ConversionSaleRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->conversionIntegration->id,
        'external_id' => 'conv-1',
        'raw_data' => ['customVariable1' => 'val', 'customVariable1-TS' => 'campaignid'],
    ]);
    ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'raw_data_id' => $convRaw->id,
        'integration_id' => $this->conversionIntegration->id,
        'external_id' => 'conv-1',
        'revenue' => 0,
        'converted_at' => now(),
    ]);

    $this->processor->processKeys($this->connector);

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

it('resolves Voluum custom variables via -TS CASE expression', function () {
    $campaignId = 'abc-123-campaign';

    $rawData = ConversionSaleRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->conversionIntegration->id,
        'external_id' => 'conv-1',
        'raw_data' => [
            'customVariable1' => 'some-other-value',
            'customVariable1-TS' => 'otherid',
            'customVariable3' => $campaignId,
            'customVariable3-TS' => 'campaignid',
        ],
    ]);

    $conversion = ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'raw_data_id' => $rawData->id,
        'integration_id' => $this->conversionIntegration->id,
        'external_id' => 'conv-1',
        'revenue' => 50,
        'converted_at' => now(),
    ]);

    // Also need campaign side data for processKeys to work
    $campRaw = CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->campaignIntegration->id,
        'external_id' => 'camp-1',
        'raw_data' => ['from_email' => $campaignId],
    ]);
    CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'raw_data_id' => $campRaw->id,
        'integration_id' => $this->campaignIntegration->id,
        'effort_id' => $this->effort->id,
        'external_id' => 'camp-1',
        'from_email' => $campaignId,
    ]);

    $this->processor->processKeys($this->connector);

    $convRecordKey = AttributionRecordKey::where('record_type', 'conversion_sale')
        ->where('record_id', $conversion->id)
        ->first();

    expect($convRecordKey)->not->toBeNull();

    $key = AttributionKey::find($convRecordKey->attribution_key_id);
    expect($key->key_value)->toBe($campaignId);
});

it('handles Voluum -TS mapping where same friendly name maps to different customVariableN across rows', function () {
    // Row 1: campaignid is in customVariable1
    $raw1 = ConversionSaleRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->conversionIntegration->id,
        'external_id' => 'conv-1',
        'raw_data' => [
            'customVariable1' => 'campaign-A',
            'customVariable1-TS' => 'campaignid',
        ],
    ]);

    // Row 2: campaignid is in customVariable5
    $raw2 = ConversionSaleRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->conversionIntegration->id,
        'external_id' => 'conv-2',
        'raw_data' => [
            'customVariable5' => 'campaign-B',
            'customVariable5-TS' => 'campaignid',
        ],
    ]);

    $conv1 = ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'raw_data_id' => $raw1->id,
        'integration_id' => $this->conversionIntegration->id,
        'external_id' => 'conv-1',
        'revenue' => 100,
        'converted_at' => now(),
    ]);
    $conv2 = ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'raw_data_id' => $raw2->id,
        'integration_id' => $this->conversionIntegration->id,
        'external_id' => 'conv-2',
        'revenue' => 200,
        'converted_at' => now(),
    ]);

    // Campaign side
    foreach (['campaign-A', 'campaign-B'] as $i => $val) {
        $campRaw = CampaignEmailRawData::create([
            'workspace_id' => $this->workspace->id,
            'integration_id' => $this->campaignIntegration->id,
            'external_id' => "camp-{$i}",
            'raw_data' => ['from_email' => $val],
        ]);
        CampaignEmail::create([
            'workspace_id' => $this->workspace->id,
            'raw_data_id' => $campRaw->id,
            'integration_id' => $this->campaignIntegration->id,
            'effort_id' => $this->effort->id,
            'external_id' => "camp-{$i}",
            'from_email' => $val,
        ]);
    }

    $this->processor->processKeys($this->connector);

    $key1 = AttributionRecordKey::where('record_type', 'conversion_sale')
        ->where('record_id', $conv1->id)->first();
    $key2 = AttributionRecordKey::where('record_type', 'conversion_sale')
        ->where('record_id', $conv2->id)->first();

    expect($key1)->not->toBeNull();
    expect($key2)->not->toBeNull();

    $keyValue1 = AttributionKey::find($key1->attribution_key_id);
    $keyValue2 = AttributionKey::find($key2->attribution_key_id);

    expect($keyValue1->key_value)->toBe('campaign-A');
    expect($keyValue2->key_value)->toBe('campaign-B');
});

it('is idempotent: re-processing produces same keys without duplicates', function () {
    $campRaw = CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->campaignIntegration->id,
        'external_id' => 'camp-1',
        'raw_data' => ['from_email' => 'test@example.com'],
    ]);
    CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'raw_data_id' => $campRaw->id,
        'integration_id' => $this->campaignIntegration->id,
        'effort_id' => $this->effort->id,
        'external_id' => 'camp-1',
        'from_email' => 'test@example.com',
    ]);

    $convRaw = ConversionSaleRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->conversionIntegration->id,
        'external_id' => 'conv-1',
        'raw_data' => [
            'customVariable1' => 'test@example.com',
            'customVariable1-TS' => 'campaignid',
        ],
    ]);
    ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'raw_data_id' => $convRaw->id,
        'integration_id' => $this->conversionIntegration->id,
        'external_id' => 'conv-1',
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

it('processes campaign email clicks and links them to keys via parent raw_data', function () {
    $email = 'clicker@example.com';

    $campRaw = CampaignEmailRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->campaignIntegration->id,
        'external_id' => 'camp-1',
        'raw_data' => ['from_email' => $email],
    ]);

    $campaign = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'raw_data_id' => $campRaw->id,
        'integration_id' => $this->campaignIntegration->id,
        'effort_id' => $this->effort->id,
        'external_id' => 'camp-1',
        'from_email' => $email,
    ]);

    $click = CampaignEmailClick::create([
        'workspace_id' => $this->workspace->id,
        'campaign_email_id' => $campaign->id,
        'clicked_at' => now()->subDay(),
    ]);

    // Seed Voluum conversion raw data so 'campaignid' field validates
    $convRaw = ConversionSaleRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->conversionIntegration->id,
        'external_id' => 'conv-1',
        'raw_data' => ['customVariable1' => 'val', 'customVariable1-TS' => 'campaignid'],
    ]);
    ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'raw_data_id' => $convRaw->id,
        'integration_id' => $this->conversionIntegration->id,
        'external_id' => 'conv-1',
        'revenue' => 0,
        'converted_at' => now(),
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

it('rejects field names not in matchable fields whitelist', function () {
    $this->connector->field_mappings = [['campaign' => 'malicious_field', 'conversion' => 'campaignid']];
    $this->connector->save();

    expect(fn () => $this->processor->processKeys($this->connector))
        ->toThrow(\InvalidArgumentException::class, "Field 'malicious_field' is not a valid matchable field");
});
