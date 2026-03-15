<?php

use App\Contracts\Integrations\PlatformConnector;
use App\Exceptions\UnsupportedDataTypeException;
use App\Http\Integrations\ActiveCampaign\ActiveCampaignConnector;
use App\Http\Integrations\ActiveCampaign\Requests\GetAccountRequest;
use App\Http\Integrations\ActiveCampaign\Requests\GetCampaignReportLinksRequest;
use App\Http\Integrations\ActiveCampaign\Requests\GetCampaignsRequest;
use App\Http\Integrations\ActiveCampaign\Requests\GetMessagesRequest;
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
        'name' => 'AC Test',
        'platform' => 'activecampaign',
        'is_active' => true,
        'data_types' => ['campaign_emails', 'campaign_email_clicks'],
        'sync_interval_minutes' => 60,
    ]);
    $this->integration->workspace_id = $this->workspace->id;
    $this->integration->organization_id = $this->org->id;
    $this->integration->save();
    $this->integration->credentials = [
        'api_url' => 'https://test.api-us1.com',
        'api_key' => 'test-key-123',
    ];
    $this->integration->save();

});

it('implements PlatformConnector and returns activecampaign as platform', function () {
    $connector = new ActiveCampaignConnector($this->integration);

    expect($connector->platform())->toBe('activecampaign');
    expect($connector)->toBeInstanceOf(PlatformConnector::class);
});

it('tests connection via /api/3/users/me', function () {
    $mockClient = new MockClient([
        GetAccountRequest::class => MockResponse::make([
            'user' => ['username' => 'testuser'],
        ]),
    ]);

    $connector = new ActiveCampaignConnector($this->integration);
    $connector->withMockClient($mockClient);

    $result = $connector->testConnection();

    expect($result->success)->toBeTrue();
    expect($result->message)->toContain('testuser');
});

it('fetches campaign emails with 3-pass approach', function () {
    $mockClient = new MockClient([
        GetCampaignsRequest::class => MockResponse::make([
            'campaigns' => [
                [
                    'id' => 1,
                    'name' => 'Test Campaign',
                    'subject' => 'Hello World',
                    'type' => 'single',
                    'status' => '2',
                    'send_amt' => 1000,
                    'delivered' => 950,
                    'opens' => 300,
                    'uniqueopens' => 200,
                    'linkclicks' => 50,
                    'uniquelinkclicks' => 30,
                    'unsubscribes' => 5,
                    'hardbounces' => 10,
                    'softbounces' => 5,
                    'sdate' => '2025-01-01 10:00:00',
                    'cdate' => '2024-12-31 09:00:00',
                ],
            ],
            'campaignMessages' => [
                [
                    'campaignid' => '1',
                    'messageid' => '10',
                    'subject' => 'Hello World',
                ],
            ],
            'meta' => ['total' => 1],
        ]),
        GetMessagesRequest::class => MockResponse::make([
            'messages' => [
                [
                    'id' => '10',
                    'fromname' => 'Test Sender',
                    'fromemail' => 'sender@test.com',
                ],
            ],
            'meta' => ['total' => 1],
        ]),
    ]);

    $connector = new ActiveCampaignConnector($this->integration);
    $connector->withMockClient($mockClient);

    $results = $connector->fetchCampaignEmails($this->integration);

    expect($results)->toHaveCount(1);
    expect($results->first()['external_id'])->toBe('1');
    expect($results->first()['name'])->toBe('Test Campaign');
    expect($results->first()['subject'])->toBe('Hello World');
    expect($results->first()['from_name'])->toBe('Test Sender');
    expect($results->first()['from_email'])->toBe('sender@test.com');
    expect($results->first()['type'])->toBe('broadcast');
    expect($results->first()['status'])->toBe('sent');
    expect($results->first()['sent'])->toBe(1000);
    expect($results->first()['delivered'])->toBe(950);
    expect($results->first()['opens'])->toBe(300);
    expect($results->first()['clicks'])->toBe(50);
    expect($results->first()['bounces'])->toBe(15);
});

it('fetches campaign email clicks via V1 campaign_report_link_list', function () {
    $mockClient = new MockClient([
        GetCampaignsRequest::class => MockResponse::make([
            'campaigns' => [
                ['id' => 1, 'name' => 'Test'],
            ],
            'meta' => ['total' => 1],
        ]),
        GetCampaignReportLinksRequest::class => MockResponse::make([
            // V1 API returns numeric-keyed link objects
            '0' => [
                'link' => 'https://example.com/offer?utm_source=ac&utm_medium=email',
                'info' => [
                    [
                        'email' => 'TEST@example.com',
                        'tstamp_iso' => '2025-01-02 14:30:00',
                    ],
                ],
            ],
            'result_code' => 1,
        ]),
    ]);

    $connector = new ActiveCampaignConnector($this->integration);
    $connector->withMockClient($mockClient);

    $results = $connector->fetchCampaignEmailClicks($this->integration);

    expect($results)->toHaveCount(1);
    $click = $results->first();
    expect($click['external_campaign_id'])->toBe('1');
    expect($click['subscriber_email_hash'])->toBe(
        hash('sha256', 'test@example.com')
    );
    expect($click['click_url'])->toBe('https://example.com/offer?utm_source=ac&utm_medium=email');
    expect($click['url_params'])->toHaveKey('utm_source', 'ac');
    expect($click['url_params'])->toHaveKey('utm_medium', 'email');
    expect($click['clicked_at'])->toBe('2025-01-02 14:30:00');
});

it('throws UnsupportedDataTypeException for fetchConversionSales', function () {
    $connector = new ActiveCampaignConnector($this->integration);

    expect(fn () => $connector->fetchConversionSales($this->integration))
        ->toThrow(UnsupportedDataTypeException::class);
});

it('supports campaign_emails and campaign_email_clicks data types', function () {
    $connector = new ActiveCampaignConnector($this->integration);

    expect($connector->supportsDataType('campaign_emails'))->toBeTrue();
    expect($connector->supportsDataType('campaign_email_clicks'))->toBeTrue();
    expect($connector->supportsDataType('conversion_sales'))->toBeFalse();
});
