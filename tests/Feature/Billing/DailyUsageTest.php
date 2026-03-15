<?php

use App\Jobs\AggregateDailyUsage;
use App\Models\CampaignEmail;
use App\Models\DailyUsage;
use App\Models\Organization;
use App\Models\Workspace;

beforeEach(function () {
    $this->org = Organization::create(['name' => 'Test Org']);
    $this->workspace = new Workspace(['name' => 'Default']);
    $this->workspace->organization_id = $this->org->id;
    $this->workspace->is_default = true;
    $this->workspace->save();
});

it('creates daily usage records for each workspace', function () {
    AggregateDailyUsage::dispatchSync();

    $usages = DailyUsage::all();
    expect($usages)->toHaveCount(1);
    expect($usages->first()->workspace_id)->toBe($this->workspace->id);
    expect($usages->first()->organization_id)->toBe($this->org->id);
    expect($usages->first()->recorded_on->toDateString())->toBe(now()->toDateString());
});

it('upserts on same day (no duplicates)', function () {
    AggregateDailyUsage::dispatchSync();
    expect(DailyUsage::count())->toBe(1);

    // Add some campaign emails
    CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Test Campaign',
        'external_id' => 'ext-1',
    ]);

    AggregateDailyUsage::dispatchSync();

    // Still one record, but updated
    expect(DailyUsage::count())->toBe(1);
    expect(DailyUsage::first()->campaigns_synced)->toBe(1);
});

it('handles missing fact tables gracefully', function () {
    // The integrations table doesn't exist, but the job should not fail
    AggregateDailyUsage::dispatchSync();

    $usage = DailyUsage::first();
    expect($usage)->not->toBeNull();
    expect($usage->active_integrations)->toBe(0);
});

it('filters by date using scopeForDate', function () {
    DailyUsage::create([
        'organization_id' => $this->org->id,
        'workspace_id' => $this->workspace->id,
        'recorded_on' => '2026-03-14',
        'campaigns_synced' => 5,
        'conversions_synced' => 3,
        'active_integrations' => 1,
    ]);

    DailyUsage::create([
        'organization_id' => $this->org->id,
        'workspace_id' => $this->workspace->id,
        'recorded_on' => '2026-03-15',
        'campaigns_synced' => 10,
        'conversions_synced' => 6,
        'active_integrations' => 2,
    ]);

    $results = DailyUsage::forDate('2026-03-14')->get();
    expect($results)->toHaveCount(1);
    expect($results->first()->campaigns_synced)->toBe(5);
});

it('filters by organization using scopeForOrganization', function () {
    $otherOrg = Organization::create(['name' => 'Other Org']);
    $otherWorkspace = new Workspace(['name' => 'Other']);
    $otherWorkspace->organization_id = $otherOrg->id;
    $otherWorkspace->is_default = true;
    $otherWorkspace->save();

    DailyUsage::create([
        'organization_id' => $this->org->id,
        'workspace_id' => $this->workspace->id,
        'recorded_on' => now()->toDateString(),
        'campaigns_synced' => 5,
        'conversions_synced' => 3,
        'active_integrations' => 1,
    ]);

    DailyUsage::create([
        'organization_id' => $otherOrg->id,
        'workspace_id' => $otherWorkspace->id,
        'recorded_on' => now()->toDateString(),
        'campaigns_synced' => 10,
        'conversions_synced' => 6,
        'active_integrations' => 2,
    ]);

    $results = DailyUsage::forOrganization($this->org->id)->get();
    expect($results)->toHaveCount(1);
    expect($results->first()->organization_id)->toBe($this->org->id);
});

it('counts campaign emails correctly', function () {
    CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Campaign 1',
        'external_id' => 'ext-1',
    ]);

    CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Campaign 2',
        'external_id' => 'ext-2',
    ]);

    AggregateDailyUsage::dispatchSync();

    expect(DailyUsage::first()->campaigns_synced)->toBe(2);
});
