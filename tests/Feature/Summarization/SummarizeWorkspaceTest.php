<?php

use App\Jobs\Summarization\SummarizeCampaigns;
use App\Jobs\Summarization\SummarizeConversions;
use App\Jobs\Summarization\SummarizeWorkspace;
use App\Models\CampaignEmail;
use App\Models\ConversionSale;
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

it('combines campaign and conversion summaries after full pipeline', function () {
    CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'c1',
        'sent' => 500,
        'opens' => 100,
        'clicks' => 50,
        'sent_at' => '2026-03-01',
    ]);

    ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'cv1',
        'revenue' => 1000.00,
        'cost' => 200.00,
        'converted_at' => '2026-03-01',
    ]);

    // Run pipeline
    (new SummarizeCampaigns($this->workspace->id))->handle();
    (new SummarizeConversions($this->workspace->id))->handle();
    (new SummarizeWorkspace($this->workspace->id))->handle();

    $summary = DB::table('summary_workspace_daily')
        ->where('workspace_id', $this->workspace->id)
        ->where('summary_date', '2026-03-01')
        ->first();

    expect($summary)->not->toBeNull();
    expect($summary->campaigns_count)->toBe(1);
    expect($summary->sent)->toBe(500);
    expect($summary->opens)->toBe(100);
    expect($summary->clicks)->toBe(50);
    expect($summary->conversions_count)->toBe(1);
    expect((float) $summary->revenue)->toBe(1000.00);
    expect((float) $summary->cost)->toBe(200.00);
});

it('handles dates with only campaign data', function () {
    CampaignEmail::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'c1',
        'sent' => 100,
        'opens' => 30,
        'clicks' => 10,
        'sent_at' => '2026-03-01',
    ]);

    (new SummarizeCampaigns($this->workspace->id))->handle();
    (new SummarizeWorkspace($this->workspace->id))->handle();

    $summary = DB::table('summary_workspace_daily')
        ->where('workspace_id', $this->workspace->id)
        ->where('summary_date', '2026-03-01')
        ->first();

    expect($summary)->not->toBeNull();
    expect($summary->sent)->toBe(100);
    expect($summary->conversions_count)->toBe(0);
    expect((float) $summary->revenue)->toBe(0.00);
});

it('handles dates with only conversion data', function () {
    ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'cv1',
        'revenue' => 500.00,
        'cost' => 100.00,
        'converted_at' => '2026-03-01',
    ]);

    (new SummarizeConversions($this->workspace->id))->handle();
    (new SummarizeWorkspace($this->workspace->id))->handle();

    $summary = DB::table('summary_workspace_daily')
        ->where('workspace_id', $this->workspace->id)
        ->where('summary_date', '2026-03-01')
        ->first();

    expect($summary)->not->toBeNull();
    expect($summary->sent)->toBe(0);
    expect($summary->conversions_count)->toBe(1);
    expect((float) $summary->revenue)->toBe(500.00);
});
