<?php

use App\Jobs\Summarization\SummarizeCampaigns;
use App\Models\CampaignEmail;
use App\Models\Integration;
use App\Models\Organization;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->org = Organization::create(['name' => 'Test Org']);
    $this->workspace = new Workspace(['name' => 'Default']);
    $this->workspace->organization_id = $this->org->id;
    $this->workspace->is_default = true;
    $this->workspace->save();

    $this->integration = new Integration([
        'name' => 'Test',
        'platform' => 'activecampaign',
        'data_types' => ['campaign_emails'],
        'is_active' => true,
        'sync_interval_minutes' => 60,
    ]);
    $this->integration->workspace_id = $this->workspace->id;
    $this->integration->organization_id = $this->org->id;
    $this->integration->save();
});

it('aggregates multi-date campaign data into summary_campaign_daily', function () {
    CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'c1',
        'name' => 'Email 1',
        'sent' => 100,
        'delivered' => 90,
        'bounced' => 5,
        'complaints' => 1,
        'unsubscribes' => 2,
        'opens' => 30,
        'unique_opens' => 25,
        'clicks' => 10,
        'unique_clicks' => 8,
        'platform_revenue' => 50.00,
        'sent_at' => '2026-03-01 10:00:00',
    ]);

    CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'c2',
        'name' => 'Email 2',
        'sent' => 200,
        'delivered' => 190,
        'bounced' => 3,
        'complaints' => 0,
        'unsubscribes' => 1,
        'opens' => 60,
        'unique_opens' => 50,
        'clicks' => 20,
        'unique_clicks' => 15,
        'platform_revenue' => 100.00,
        'sent_at' => '2026-03-01 14:00:00',
    ]);

    CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'c3',
        'name' => 'Email 3',
        'sent' => 50,
        'delivered' => 48,
        'opens' => 15,
        'unique_opens' => 12,
        'clicks' => 5,
        'unique_clicks' => 4,
        'platform_revenue' => 25.00,
        'sent_at' => '2026-03-02 10:00:00',
    ]);

    (new SummarizeCampaigns($this->workspace->id))->handle();

    // March 1 should have 2 campaigns aggregated
    $march1 = DB::table('summary_campaign_daily')
        ->where('workspace_id', $this->workspace->id)
        ->where('summary_date', '2026-03-01')
        ->first();

    expect($march1)->not->toBeNull();
    expect($march1->campaigns_count)->toBe(2);
    expect($march1->sent)->toBe(300);
    expect($march1->delivered)->toBe(280);
    expect($march1->bounced)->toBe(8);
    expect($march1->complaints)->toBe(1);
    expect($march1->unsubscribes)->toBe(3);
    expect($march1->opens)->toBe(90);
    expect($march1->unique_opens)->toBe(75);
    expect($march1->clicks)->toBe(30);
    expect($march1->unique_clicks)->toBe(23);
    expect((float) $march1->platform_revenue)->toBe(150.00);

    // March 2 should have 1 campaign
    $march2 = DB::table('summary_campaign_daily')
        ->where('workspace_id', $this->workspace->id)
        ->where('summary_date', '2026-03-02')
        ->first();

    expect($march2)->not->toBeNull();
    expect($march2->campaigns_count)->toBe(1);
    expect($march2->sent)->toBe(50);
});

it('aggregates by platform into summary_campaign_by_platform', function () {
    // Create a second integration with different platform
    $integration2 = new Integration([
        'name' => 'ES',
        'platform' => 'expertsender',
        'data_types' => ['campaign_emails'],
        'is_active' => true,
        'sync_interval_minutes' => 60,
    ]);
    $integration2->workspace_id = $this->workspace->id;
    $integration2->organization_id = $this->org->id;
    $integration2->save();

    CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'ac1',
        'sent' => 100,
        'sent_at' => '2026-03-01',
    ]);

    CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $integration2->id,
        'external_id' => 'es1',
        'sent' => 200,
        'sent_at' => '2026-03-01',
    ]);

    (new SummarizeCampaigns($this->workspace->id))->handle();

    $acSummary = DB::table('summary_campaign_by_platform')
        ->where('workspace_id', $this->workspace->id)
        ->where('platform', 'activecampaign')
        ->first();

    $esSummary = DB::table('summary_campaign_by_platform')
        ->where('workspace_id', $this->workspace->id)
        ->where('platform', 'expertsender')
        ->first();

    expect($acSummary)->not->toBeNull();
    expect($acSummary->sent)->toBe(100);

    expect($esSummary)->not->toBeNull();
    expect($esSummary->sent)->toBe(200);
});

it('incremental mode only processes updated records', function () {
    $oldEmail = CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'old1',
        'sent' => 100,
        'sent_at' => '2026-02-01',
    ]);

    DB::table('campaign_emails')->where('id', $oldEmail->id)->update(['updated_at' => '2026-02-01 00:00:00']);

    CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'new1',
        'sent' => 200,
        'sent_at' => '2026-03-01',
    ]);

    (new SummarizeCampaigns($this->workspace->id, Carbon::parse('2026-03-01')))->handle();

    $marchSummary = DB::table('summary_campaign_daily')
        ->where('workspace_id', $this->workspace->id)
        ->where('summary_date', '2026-03-01')
        ->first();

    $febSummary = DB::table('summary_campaign_daily')
        ->where('workspace_id', $this->workspace->id)
        ->where('summary_date', '2026-02-01')
        ->first();

    expect($marchSummary)->not->toBeNull();
    expect($marchSummary->sent)->toBe(200);
    expect($febSummary)->toBeNull();
});
