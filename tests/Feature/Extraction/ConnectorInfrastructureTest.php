<?php

use App\Contracts\Integrations\PlatformConnector;
use App\Exceptions\InvalidConnectorUrlException;
use App\Exceptions\UnsupportedDataTypeException;
use App\Http\Integrations\BasePlatformConnector;
use App\Models\Organization;
use App\Models\Workspace;
use App\Services\Integrations\ConnectorRegistry;

beforeEach(function () {
    $this->org = Organization::create(['name' => 'Test Org']);
    $this->workspace = new Workspace(['name' => 'Default']);
    $this->workspace->organization_id = $this->org->id;
    $this->workspace->is_default = true;
    $this->workspace->save();
});

// ── Config Tests ──────────────────────────────────────────────────────

it('returns the platform registry from config', function () {
    $platforms = config('integrations.platforms');

    expect($platforms)->toBeArray()
        ->and(array_keys($platforms))->toBe(['activecampaign', 'expertsender', 'maropost', 'voluum']);
});

it('has connector class, label, data_types, and credential_fields for each platform', function () {
    $platforms = config('integrations.platforms');

    foreach ($platforms as $slug => $config) {
        expect($config)->toHaveKeys(['connector', 'label', 'data_types', 'credential_fields'])
            ->and($config['data_types'])->toBeArray()
            ->and($config['credential_fields'])->toBeArray();
    }
});

// ── ConnectorRegistry Tests ───────────────────────────────────────────

it('returns all platform slugs', function () {
    $registry = app(ConnectorRegistry::class);

    expect($registry->platforms())->toBe(['activecampaign', 'expertsender', 'maropost', 'voluum']);
});

it('returns config for a specific platform', function () {
    $registry = app(ConnectorRegistry::class);

    $config = $registry->platformConfig('activecampaign');

    expect($config['label'])->toBe('ActiveCampaign')
        ->and($config['data_types'])->toContain('campaign_emails');
});

it('throws for unknown platform slug', function () {
    $registry = app(ConnectorRegistry::class);

    expect(fn () => $registry->platformConfig('unknown'))
        ->toThrow(InvalidArgumentException::class, 'not registered');
});

// ── PlatformConnector Interface Tests ─────────────────────────────────

it('defines the expected interface methods', function () {
    $reflection = new ReflectionClass(PlatformConnector::class);

    expect($reflection->hasMethod('fetchCampaignEmails'))->toBeTrue()
        ->and($reflection->hasMethod('fetchCampaignEmailClicks'))->toBeTrue()
        ->and($reflection->hasMethod('fetchConversionSales'))->toBeTrue()
        ->and($reflection->hasMethod('supportsDataType'))->toBeTrue()
        ->and($reflection->hasMethod('platform'))->toBeTrue();
});

// ── BasePlatformConnector Tests ───────────────────────────────────────

it('throws UnsupportedDataTypeException for unsupported data types', function () {
    $integration = $this->workspace->integrations()->create([
        'name' => 'Test',
        'platform' => 'activecampaign',
    ]);

    // Create a concrete test connector
    $connector = new class($integration) extends BasePlatformConnector
    {
        public function resolveBaseUrl(): string
        {
            return 'https://example.com';
        }

        public function platform(): string
        {
            return 'test_platform';
        }
    };

    expect(fn () => $connector->fetchConversionSales($integration))
        ->toThrow(UnsupportedDataTypeException::class);
});

it('checks data type support against config', function () {
    $integration = $this->workspace->integrations()->create([
        'name' => 'AC Test',
        'platform' => 'activecampaign',
    ]);

    $connector = new class($integration) extends BasePlatformConnector
    {
        public function resolveBaseUrl(): string
        {
            return 'https://example.com';
        }

        public function platform(): string
        {
            return 'activecampaign';
        }
    };

    expect($connector->supportsDataType('campaign_emails'))->toBeTrue()
        ->and($connector->supportsDataType('conversion_sales'))->toBeFalse();
});

// ── SSRF Protection Tests ─────────────────────────────────────────────

it('rejects non-HTTPS URLs', function () {
    $integration = $this->workspace->integrations()->create([
        'name' => 'SSRF Test',
        'platform' => 'activecampaign',
    ]);

    $connector = new class($integration) extends BasePlatformConnector
    {
        public function resolveBaseUrl(): string
        {
            return 'https://example.com';
        }

        public function platform(): string
        {
            return 'test';
        }

        public function testValidateUrl(string $url): string
        {
            return $this->validateUrl($url);
        }
    };

    expect(fn () => $connector->testValidateUrl('http://example.com'))
        ->toThrow(InvalidConnectorUrlException::class, 'HTTPS');
});

it('rejects URLs with non-standard ports', function () {
    $integration = $this->workspace->integrations()->create([
        'name' => 'Port Test',
        'platform' => 'activecampaign',
    ]);

    $connector = new class($integration) extends BasePlatformConnector
    {
        public function resolveBaseUrl(): string
        {
            return 'https://example.com';
        }

        public function platform(): string
        {
            return 'test';
        }

        public function testValidateUrl(string $url): string
        {
            return $this->validateUrl($url);
        }
    };

    expect(fn () => $connector->testValidateUrl('https://example.com:8080'))
        ->toThrow(InvalidConnectorUrlException::class, 'non-standard port');
});

// ── HTTP Client Defaults ──────────────────────────────────────────────

it('has configurable HTTP timeouts', function () {
    expect(config('integrations.http.connect_timeout'))->toBe(10)
        ->and(config('integrations.http.timeout'))->toBe(30)
        ->and(config('integrations.http.max_response_size'))->toBe(50 * 1024 * 1024);
});
