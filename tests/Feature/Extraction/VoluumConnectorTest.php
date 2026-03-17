<?php

use App\Contracts\Integrations\PlatformConnector;
use App\Exceptions\UnsupportedDataTypeException;
use App\Http\Integrations\Voluum\Requests\AuthenticateRequest;
use App\Http\Integrations\Voluum\Requests\GetConversionsRequest;
use App\Http\Integrations\Voluum\VoluumConnector;
use App\Models\Integration;
use App\Models\Organization;
use App\Models\Workspace;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    $this->org = Organization::create(['name' => 'Test Org']);
    $this->workspace = new Workspace(['name' => 'Default']);
    $this->workspace->organization_id = $this->org->id;
    $this->workspace->is_default = true;
    $this->workspace->save();

    $this->integration = new Integration([
        'name' => 'Voluum Test',
        'platform' => 'voluum',
        'is_active' => true,
        'data_types' => ['conversion_sales'],
        'sync_interval_minutes' => 60,
    ]);
    $this->integration->workspace_id = $this->workspace->id;
    $this->integration->organization_id = $this->org->id;
    $this->integration->save();
    $this->integration->credentials = [
        'access_key_id' => 'test-access-id',
        'access_key_secret' => 'test-access-secret',
    ];
    $this->integration->save();
});

it('implements PlatformConnector and returns voluum as platform', function () {
    $connector = new VoluumConnector($this->integration);

    expect($connector->platform())->toBe('voluum');
    expect($connector)->toBeInstanceOf(PlatformConnector::class);
});

it('authenticates and caches session token', function () {
    $mockClient = new MockClient([
        AuthenticateRequest::class => MockResponse::make([
            'token' => 'test-session-token-xyz',
        ]),
        GetConversionsRequest::class => MockResponse::make([
            'rows' => [],
        ]),
    ]);

    $connector = new VoluumConnector($this->integration);
    $connector->withMockClient($mockClient);

    // Should authenticate and then fetch conversions
    $results = $connector->fetchConversionSales($this->integration);

    expect($results)->toBeEmpty();
    $mockClient->assertSent(AuthenticateRequest::class);
});

it('fetches conversion sales with normalized data', function () {
    $mockClient = new MockClient([
        AuthenticateRequest::class => MockResponse::make([
            'token' => 'test-token',
        ]),
        GetConversionsRequest::class => MockResponse::make([
            'rows' => [
                [
                    'clickId' => 'conv-001',
                    'revenue' => 99.99,
                    'payout' => 45.50,
                    'cost' => 12.50,
                    'conversionTimestamp' => '2025-01-03T12:00:00Z',
                    'campaignId' => 'camp-001',
                    'offerId' => 'offer-001',
                    'country' => 'US',
                ],
            ],
        ]),
    ]);

    $connector = new VoluumConnector($this->integration);
    $connector->withMockClient($mockClient);

    $results = $connector->fetchConversionSales($this->integration);

    expect($results)->toHaveCount(1);
    $sale = $results->first();
    expect($sale['external_id'])->toBe('conv-001');
    expect($sale['revenue'])->toBe(99.99);
    expect($sale['payout'])->toBe(45.50);
    expect($sale['cost'])->toBe(12.50);
    expect($sale['campaignId'])->toBe('camp-001');
});

it('throws UnsupportedDataTypeException for fetchCampaignEmails', function () {
    $connector = new VoluumConnector($this->integration);

    expect(fn () => $connector->fetchCampaignEmails($this->integration))
        ->toThrow(UnsupportedDataTypeException::class);
});

it('throws UnsupportedDataTypeException for fetchCampaignEmailClicks', function () {
    $connector = new VoluumConnector($this->integration);

    expect(fn () => $connector->fetchCampaignEmailClicks($this->integration))
        ->toThrow(UnsupportedDataTypeException::class);
});

it('supports conversion_sales data type only', function () {
    $connector = new VoluumConnector($this->integration);

    expect($connector->supportsDataType('conversion_sales'))->toBeTrue();
    expect($connector->supportsDataType('campaign_emails'))->toBeFalse();
    expect($connector->supportsDataType('campaign_email_clicks'))->toBeFalse();
});
