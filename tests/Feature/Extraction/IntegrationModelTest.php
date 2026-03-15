<?php

use App\Models\Integration;
use App\Models\Organization;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->org = Organization::create(['name' => 'Test Org']);
    $this->workspace = new Workspace(['name' => 'Default']);
    $this->workspace->organization_id = $this->org->id;
    $this->workspace->is_default = true;
    $this->workspace->save();
});

it('creates integration with all fields', function () {
    $integration = $this->workspace->integrations()->create([
        'name' => 'My AC',
        'platform' => 'activecampaign',
        'data_types' => ['campaign_emails', 'campaign_email_clicks'],
        'is_active' => true,
        'sync_interval_minutes' => 30,
        'settings' => ['custom' => 'value'],
    ]);

    expect($integration)->toBeInstanceOf(Integration::class)
        ->and($integration->name)->toBe('My AC')
        ->and($integration->platform)->toBe('activecampaign')
        ->and($integration->data_types)->toBe(['campaign_emails', 'campaign_email_clicks'])
        ->and($integration->is_active)->toBeTrue()
        ->and($integration->sync_interval_minutes)->toBe(30)
        ->and($integration->settings)->toBe(['custom' => 'value']);
});

it('auto-sets organization_id from workspace on create', function () {
    $integration = $this->workspace->integrations()->create([
        'name' => 'Auto Org',
        'platform' => 'activecampaign',
    ]);

    expect($integration->organization_id)->toBe($this->org->id);
});

it('encrypts and decrypts credentials transparently', function () {
    $integration = $this->workspace->integrations()->create([
        'name' => 'Encrypted',
        'platform' => 'activecampaign',
    ]);

    $integration->setCredentials([
        'api_url' => 'https://account.api-us1.com',
        'api_key' => 'secret-key-123',
    ]);

    $integration->refresh();
    expect($integration->credentials)->toBe([
        'api_url' => 'https://account.api-us1.com',
        'api_key' => 'secret-key-123',
    ]);

    // Verify it's actually encrypted in DB
    $raw = DB::table('integrations')
        ->where('id', $integration->id)
        ->value('credentials');
    expect($raw)->not->toContain('secret-key-123');
});

it('validates credential fields against platform config', function () {
    $integration = $this->workspace->integrations()->create([
        'name' => 'Bad Creds',
        'platform' => 'activecampaign',
    ]);

    expect(fn () => $integration->setCredentials(['api_url' => 'test']))
        ->toThrow(InvalidArgumentException::class, 'Missing required credential fields');
});

it('casts data_types and sync_statuses as arrays', function () {
    $integration = $this->workspace->integrations()->create([
        'name' => 'Cast Test',
        'platform' => 'activecampaign',
        'data_types' => ['campaign_emails'],
    ]);

    expect($integration->data_types)->toBeArray();
    expect($integration->sync_statuses)->toBeNull();
});

it('has workspace and organization relationships', function () {
    $integration = $this->workspace->integrations()->create([
        'name' => 'Rel Test',
        'platform' => 'activecampaign',
    ]);

    expect($integration->workspace->id)->toBe($this->workspace->id)
        ->and($integration->organization->id)->toBe($this->org->id);
});

it('uses active scope', function () {
    $this->workspace->integrations()->create(['name' => 'Active', 'platform' => 'activecampaign', 'is_active' => true]);
    $this->workspace->integrations()->create(['name' => 'Inactive', 'platform' => 'expertsender', 'is_active' => false]);

    expect(Integration::active()->count())->toBe(1);
});

it('uses forPlatform scope', function () {
    $this->workspace->integrations()->create(['name' => 'AC', 'platform' => 'activecampaign']);
    $this->workspace->integrations()->create(['name' => 'ES', 'platform' => 'expertsender']);

    expect(Integration::forPlatform('activecampaign')->count())->toBe(1);
});

it('returns true from isDueForSync when never synced', function () {
    $integration = $this->workspace->integrations()->create([
        'name' => 'Due',
        'platform' => 'activecampaign',
        'is_active' => true,
        'sync_in_progress' => false,
    ]);

    expect($integration->isDueForSync())->toBeTrue();
});

it('returns false from isDueForSync when in progress', function () {
    $integration = $this->workspace->integrations()->create([
        'name' => 'In Progress',
        'platform' => 'activecampaign',
        'is_active' => true,
    ]);
    $integration->markSyncStarted();

    expect($integration->isDueForSync())->toBeFalse();
});

it('returns false from isDueForSync when recently synced', function () {
    $integration = $this->workspace->integrations()->create([
        'name' => 'Recent',
        'platform' => 'activecampaign',
        'is_active' => true,
        'sync_in_progress' => false,
        'sync_interval_minutes' => 60,
    ]);
    $integration->last_synced_at = now()->subMinutes(30);
    $integration->save();

    expect($integration->isDueForSync())->toBeFalse();
});

it('tracks sync status per data type', function () {
    $integration = $this->workspace->integrations()->create([
        'name' => 'Sync Track',
        'platform' => 'activecampaign',
    ]);

    $integration->markSyncStarted();
    expect($integration->sync_in_progress)->toBeTrue();

    $integration->markDataTypeCompleted('campaign_emails');
    expect($integration->sync_statuses['campaign_emails'])->toBe('completed');

    $integration->markDataTypeFailed('campaign_email_clicks', 'API error');
    expect($integration->sync_statuses['campaign_email_clicks'])->toBe('failed');

    $integration->markSyncCompleted();
    expect($integration->sync_in_progress)->toBeFalse()
        ->and($integration->last_sync_status)->toBe('completed')
        ->and($integration->last_synced_at)->not->toBeNull();
});

it('marks sync as failed', function () {
    $integration = $this->workspace->integrations()->create([
        'name' => 'Fail Track',
        'platform' => 'activecampaign',
    ]);

    $integration->markSyncStarted();
    $integration->markSyncFailed('Connection timeout');

    expect($integration->sync_in_progress)->toBeFalse()
        ->and($integration->last_sync_status)->toBe('failed')
        ->and($integration->last_sync_error)->toBe('Connection timeout');
});

it('soft deletes integration', function () {
    $integration = $this->workspace->integrations()->create([
        'name' => 'Deletable',
        'platform' => 'activecampaign',
    ]);

    $integration->delete();

    expect(Integration::find($integration->id))->toBeNull()
        ->and(Integration::withTrashed()->find($integration->id))->not->toBeNull();
});

it('workspace has integrations relationship', function () {
    $this->workspace->integrations()->create(['name' => 'WS Rel', 'platform' => 'activecampaign']);

    expect($this->workspace->integrations)->toHaveCount(1);
});
