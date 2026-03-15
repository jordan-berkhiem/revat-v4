<?php

use App\Contracts\Integrations\PlatformConnector;
use App\Exceptions\UnsupportedDataTypeException;
use App\Http\Integrations\Maropost\MaropostConnector;
use App\Http\Integrations\Maropost\Requests\GetClickReportRequest;
use App\Http\Integrations\Maropost\Requests\GraphqlCampaignsRequest;
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
        'name' => 'Maropost Test',
        'platform' => 'maropost',
        'is_active' => true,
        'data_types' => ['campaign_emails', 'campaign_email_clicks'],
        'sync_interval_minutes' => 60,
    ]);
    $this->integration->workspace_id = $this->workspace->id;
    $this->integration->organization_id = $this->org->id;
    $this->integration->save();
    $this->integration->credentials = [
        'account_id' => '12345',
        'auth_token' => 'test-token-789',
    ];
    $this->integration->save();

});

it('implements PlatformConnector and returns maropost as platform', function () {
    $connector = new MaropostConnector($this->integration);

    expect($connector->platform())->toBe('maropost');
    expect($connector)->toBeInstanceOf(PlatformConnector::class);
});

it('tests connection via GraphQL endpoint', function () {
    $mockClient = new MockClient([
        GraphqlCampaignsRequest::class => MockResponse::make([
            'data' => [
                'campaigns' => [
                    ['id' => 1, 'name' => 'Test'],
                ],
            ],
        ]),
    ]);

    $connector = new MaropostConnector($this->integration);
    $connector->withMockClient($mockClient);

    $result = $connector->testConnection();

    expect($result->success)->toBeTrue();
    expect($result->message)->toContain('12345');
});

it('fetches campaign emails via GraphQL with all stats in one query', function () {
    $mockClient = new MockClient([
        GraphqlCampaignsRequest::class => MockResponse::make([
            'data' => [
                'campaigns' => [
                    [
                        'id' => 1,
                        'name' => 'Newsletter',
                        'subject' => 'Monthly Update',
                        'from_name' => 'Marketing',
                        'from_email' => 'marketing@test.com',
                        'status' => 'sent',
                        'campaign_type' => '',
                        'sent_at' => '2025-01-01 10:00:00',
                        'send_at' => '2024-12-30 09:00:00',
                        'total_sent' => 2000,
                        'total_opens' => 600,
                        'total_unique_opens' => 400,
                        'total_clicks' => 100,
                        'total_unique_clicks' => 60,
                        'total_unsubscribes' => 10,
                        'total_bounces' => 35,
                    ],
                ],
            ],
        ]),
    ]);

    $connector = new MaropostConnector($this->integration);
    $connector->withMockClient($mockClient);

    $results = $connector->fetchCampaignEmails($this->integration);

    expect($results)->toHaveCount(1);
    expect($results->first()['external_id'])->toBe('1');
    expect($results->first()['name'])->toBe('Newsletter');
    expect($results->first()['from_name'])->toBe('Marketing');
    expect($results->first()['type'])->toBe('broadcast');
    expect($results->first()['sent'])->toBe(2000);
    expect($results->first()['bounces'])->toBe(35);
});

it('fetches campaign email clicks from /reports/clicks.json with unique=true', function () {
    $mockClient = new MockClient([
        GetClickReportRequest::class => MockResponse::make([
            [
                'total_pages' => 1,
                'campaign_id' => 1,
                'contact' => ['email' => 'CLICK@Example.com'],
                'url' => 'https://example.com/landing?src=mp&cid=123',
                'recorded_at' => '2025-01-02 16:00:00',
            ],
        ]),
    ]);

    $connector = new MaropostConnector($this->integration);
    $connector->withMockClient($mockClient);

    $results = $connector->fetchCampaignEmailClicks($this->integration);

    expect($results)->toHaveCount(1);
    $click = $results->first();
    expect($click['external_campaign_id'])->toBe('1');
    expect($click['subscriber_email_hash'])->toBe(
        hash('sha256', 'click@example.com')
    );
    expect($click['url_params'])->toHaveKey('src', 'mp');
    expect($click['url_params'])->toHaveKey('cid', '123');
});

it('throws UnsupportedDataTypeException for fetchConversionSales', function () {
    $connector = new MaropostConnector($this->integration);

    expect(fn () => $connector->fetchConversionSales($this->integration))
        ->toThrow(UnsupportedDataTypeException::class);
});

it('supports campaign_emails and campaign_email_clicks data types', function () {
    $connector = new MaropostConnector($this->integration);

    expect($connector->supportsDataType('campaign_emails'))->toBeTrue();
    expect($connector->supportsDataType('campaign_email_clicks'))->toBeTrue();
    expect($connector->supportsDataType('conversion_sales'))->toBeFalse();
});
