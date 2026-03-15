<?php

use App\Http\Integrations\ActiveCampaign\ActiveCampaignConnector;
use App\Http\Integrations\ExpertSender\ExpertSenderConnector;
use App\Http\Integrations\Maropost\MaropostConnector;
use App\Http\Integrations\Voluum\VoluumConnector;
use App\Models\ConversionSaleRawData;
use App\Models\Organization;
use App\Models\Workspace;

beforeEach(function () {
    $this->org = Organization::create(['name' => 'Test Org']);
    $this->workspace = new Workspace(['name' => 'Default']);
    $this->workspace->organization_id = $this->org->id;
    $this->workspace->is_default = true;
    $this->workspace->save();
});

it('returns static matchable fields for Maropost', function () {
    $integration = $this->workspace->integrations()->create([
        'name' => 'Test Maropost',
        'platform' => 'maropost',
        'data_types' => ['campaign_emails', 'campaign_email_clicks'],
        'is_active' => true,
    ]);
    $integration->setCredentials(['account_id' => 'test', 'auth_token' => 'test']);

    $connector = new MaropostConnector($integration);
    $fields = $connector->getMatchableFields($integration);

    expect($fields)->toHaveKeys(['campaign_emails', 'campaign_email_clicks']);
    expect($fields['campaign_emails'])->toBeArray();

    $values = array_column($fields['campaign_emails'], 'value');
    expect($values)->toContain('name', 'subject', 'from_email', 'external_id');

    foreach ($fields['campaign_emails'] as $field) {
        expect($field)->toHaveKeys(['value', 'label']);
    }
});

it('returns static matchable fields for ActiveCampaign', function () {
    $integration = $this->workspace->integrations()->create([
        'name' => 'Test AC',
        'platform' => 'activecampaign',
        'data_types' => ['campaign_emails', 'campaign_email_clicks'],
        'is_active' => true,
    ]);
    $integration->setCredentials(['api_url' => 'https://test.api-us1.com', 'api_key' => 'test']);

    $connector = new ActiveCampaignConnector($integration);
    $fields = $connector->getMatchableFields($integration);

    expect($fields)->toHaveKeys(['campaign_emails', 'campaign_email_clicks']);

    $values = array_column($fields['campaign_emails'], 'value');
    expect($values)->toContain('subject', 'fromname', 'fromemail', 'external_id');
});

it('returns static matchable fields for ExpertSender', function () {
    $integration = $this->workspace->integrations()->create([
        'name' => 'Test ES',
        'platform' => 'expertsender',
        'data_types' => ['campaign_emails', 'campaign_email_clicks'],
        'is_active' => true,
    ]);
    $integration->setCredentials(['api_url' => 'https://test.expertsender.com', 'api_key' => 'test']);

    $connector = new ExpertSenderConnector($integration);
    $fields = $connector->getMatchableFields($integration);

    expect($fields)->toHaveKeys(['campaign_emails', 'campaign_email_clicks']);

    $values = array_column($fields['campaign_emails'], 'value');
    expect($values)->toContain('Subject', 'FromName', 'FromEmail', 'external_id');
});

it('returns dynamic matchable fields for Voluum from -TS values', function () {
    $integration = $this->workspace->integrations()->create([
        'name' => 'Test Voluum',
        'platform' => 'voluum',
        'data_types' => ['conversion_sales'],
        'is_active' => true,
    ]);
    $integration->setCredentials(['access_key_id' => 'test', 'access_key_secret' => 'test']);

    ConversionSaleRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $integration->id,
        'external_id' => 'conv-1',
        'raw_data' => [
            'customVariable1' => 'val1',
            'customVariable1-TS' => 'campaignid',
            'customVariable2' => 'val2',
            'customVariable2-TS' => 'offerid',
        ],
    ]);
    ConversionSaleRawData::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $integration->id,
        'external_id' => 'conv-2',
        'raw_data' => [
            'customVariable3' => 'val3',
            'customVariable3-TS' => 'campaignid',
            'customVariable4' => 'val4',
            'customVariable4-TS' => 'affiliateid',
        ],
    ]);

    $connector = new VoluumConnector($integration);
    $fields = $connector->getMatchableFields($integration);

    expect($fields)->toHaveKey('conversion_sales');

    $values = array_column($fields['conversion_sales'], 'value');
    expect($values)->toContain('campaignid', 'offerid', 'affiliateid');

    // No duplicates
    expect(count($values))->toBe(count(array_unique($values)));
});

it('returns empty array for Voluum when no raw data exists', function () {
    $integration = $this->workspace->integrations()->create([
        'name' => 'Test Voluum Empty',
        'platform' => 'voluum',
        'data_types' => ['conversion_sales'],
        'is_active' => true,
    ]);
    $integration->setCredentials(['access_key_id' => 'test', 'access_key_secret' => 'test']);

    $connector = new VoluumConnector($integration);
    $fields = $connector->getMatchableFields($integration);

    expect($fields)->toBe(['conversion_sales' => []]);
});
