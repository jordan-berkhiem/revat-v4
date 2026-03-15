<?php

use App\Contracts\Integrations\PlatformConnector;
use App\Exceptions\UnsupportedDataTypeException;
use App\Http\Integrations\ExpertSender\ExpertSenderConnector;
use App\Http\Integrations\ExpertSender\Requests\GetActivitiesRequest;
use App\Http\Integrations\ExpertSender\Requests\GetMessageStatisticsRequest;
use App\Http\Integrations\ExpertSender\Requests\GetMessagesRequest;
use App\Http\Integrations\ExpertSender\Requests\GetTimeRequest;
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
        'name' => 'ES Test',
        'platform' => 'expertsender',
        'is_active' => true,
        'data_types' => ['campaign_emails', 'campaign_email_clicks'],
        'sync_interval_minutes' => 60,
    ]);
    $this->integration->workspace_id = $this->workspace->id;
    $this->integration->organization_id = $this->org->id;
    $this->integration->save();
    $this->integration->credentials = [
        'api_url' => 'https://expertsender.com',
        'api_key' => 'test-key-456',
    ];
    $this->integration->save();

});

it('implements PlatformConnector and returns expertsender as platform', function () {
    $connector = new ExpertSenderConnector($this->integration);

    expect($connector->platform())->toBe('expertsender');
    expect($connector)->toBeInstanceOf(PlatformConnector::class);
});

it('tests connection via GetTimeRequest with timezone detection', function () {
    $mockClient = new MockClient([
        GetTimeRequest::class => MockResponse::make([
            'Data' => now('UTC')->toDateTimeString(),
        ]),
    ]);

    $connector = new ExpertSenderConnector($this->integration);
    $connector->withMockClient($mockClient);

    $result = $connector->testConnection();

    expect($result->success)->toBeTrue();
    expect($result->message)->toContain('ExpertSender');
});

it('fetches campaign emails with per-message stats from /v2/Api/MessageStatistics', function () {
    $mockClient = new MockClient([
        GetTimeRequest::class => MockResponse::make([
            'Data' => now('UTC')->toDateTimeString(),
        ]),
        GetMessagesRequest::class => MockResponse::make([
            'Data' => [
                'Messages' => [
                    [
                        'Id' => 101,
                        'Tags' => 'Welcome Email',
                        'Subject' => 'Welcome to our service',
                        'Type' => 'Newsletter',
                        'FromName' => 'Support',
                        'FromEmail' => 'support@test.com',
                        'SentDate' => '2025-01-01 12:00:00',
                        'CreatedOn' => '2024-12-30',
                    ],
                ],
            ],
        ]),
        GetMessageStatisticsRequest::class => MockResponse::make([
            'Data' => [
                'Sent' => 500,
                'Delivered' => 480,
                'Opens' => 150,
                'UniqueOpens' => 100,
                'Clicks' => 40,
                'UniqueClicks' => 25,
                'Unsubscribes' => 3,
                'Bounced' => 8,
            ],
        ]),
    ]);

    $connector = new ExpertSenderConnector($this->integration);
    $connector->withMockClient($mockClient);

    $results = $connector->fetchCampaignEmails($this->integration);

    expect($results)->toHaveCount(1);
    expect($results->first()['external_id'])->toBe('101');
    expect($results->first()['name'])->toBe('Welcome Email');
    expect($results->first()['subject'])->toBe('Welcome to our service');
    expect($results->first()['from_name'])->toBe('Support');
    expect($results->first()['from_email'])->toBe('support@test.com');
    expect($results->first()['type'])->toBe('broadcast');
    expect($results->first()['sent'])->toBe(500);
    expect($results->first()['delivered'])->toBe(480);
    expect($results->first()['opens'])->toBe(150);
    expect($results->first()['clicks'])->toBe(40);
    expect($results->first()['bounces'])->toBe(8);
});

it('fetches campaign email clicks via day-by-day CSV Activities endpoint', function () {
    // Mock: timezone detection returns UTC, then a single day of click activities
    $today = now('UTC')->format('Y-m-d');
    $mockClient = new MockClient([
        GetTimeRequest::class => MockResponse::make([
            'Data' => now('UTC')->toDateTimeString(),
        ]),
        GetActivitiesRequest::class => MockResponse::make(
            body: "Email,MessageId,Url,Date\nUSER@Example.com,101,https://example.com/promo?ref=es&campaign=welcome,2025-01-02 15:00:00\n",
            status: 200,
        ),
    ]);

    $connector = new ExpertSenderConnector($this->integration);
    $connector->withMockClient($mockClient);

    // Use a since date of today so only 1 day is fetched
    $results = $connector->fetchCampaignEmailClicks($this->integration, since: now()->startOfDay());

    expect($results)->toHaveCount(1);
    $click = $results->first();
    expect($click['external_campaign_id'])->toBe('101');
    expect($click['subscriber_email_hash'])->toBe(
        hash('sha256', 'user@example.com')
    );
    expect($click['click_url'])->toBe('https://example.com/promo?ref=es&campaign=welcome');
    expect($click['url_params'])->toHaveKey('ref', 'es');
    expect($click['url_params'])->toHaveKey('campaign', 'welcome');
});

it('throws UnsupportedDataTypeException for fetchConversionSales', function () {
    $connector = new ExpertSenderConnector($this->integration);

    expect(fn () => $connector->fetchConversionSales($this->integration))
        ->toThrow(UnsupportedDataTypeException::class);
});

it('supports campaign_emails and campaign_email_clicks data types', function () {
    $connector = new ExpertSenderConnector($this->integration);

    expect($connector->supportsDataType('campaign_emails'))->toBeTrue();
    expect($connector->supportsDataType('campaign_email_clicks'))->toBeTrue();
    expect($connector->supportsDataType('conversion_sales'))->toBeFalse();
});
