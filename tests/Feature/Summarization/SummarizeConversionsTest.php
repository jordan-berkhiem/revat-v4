<?php

use App\Jobs\Summarization\SummarizeConversions;
use App\Models\ConversionSale;
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
        'data_types' => ['conversion_sales'],
        'is_active' => true,
        'sync_interval_minutes' => 60,
    ]);
    $this->integration->workspace_id = $this->workspace->id;
    $this->integration->organization_id = $this->org->id;
    $this->integration->save();
});

it('aggregates conversion_sales across multiple dates', function () {
    ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'cv1',
        'revenue' => 100.50,
        'payout' => 50.00,
        'cost' => 10.00,
        'converted_at' => '2026-03-01',
    ]);

    ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'cv2',
        'revenue' => 200.25,
        'payout' => 75.00,
        'cost' => 20.50,
        'converted_at' => '2026-03-01',
    ]);

    ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'cv3',
        'revenue' => 50.00,
        'payout' => 25.00,
        'cost' => 5.00,
        'converted_at' => '2026-03-02',
    ]);

    (new SummarizeConversions($this->workspace->id))->handle();

    $march1 = DB::table('summary_conversion_daily')
        ->where('workspace_id', $this->workspace->id)
        ->where('summary_date', '2026-03-01')
        ->first();

    expect($march1)->not->toBeNull();
    expect($march1->conversions_count)->toBe(2);
    expect((float) $march1->revenue)->toBe(300.75);
    expect((float) $march1->payout)->toBe(125.00);
    expect((float) $march1->cost)->toBe(30.50);

    $march2 = DB::table('summary_conversion_daily')
        ->where('workspace_id', $this->workspace->id)
        ->where('summary_date', '2026-03-02')
        ->first();

    expect($march2)->not->toBeNull();
    expect($march2->conversions_count)->toBe(1);
    expect((float) $march2->revenue)->toBe(50.00);
});

it('incremental mode only processes updated records', function () {
    $old = ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'old1',
        'revenue' => 100.00,
        'converted_at' => '2026-02-01',
    ]);

    DB::table('conversion_sales')->where('id', $old->id)->update(['updated_at' => '2026-02-01 00:00:00']);

    ConversionSale::create([
        'workspace_id' => $this->workspace->id,
        'integration_id' => $this->integration->id,
        'external_id' => 'new1',
        'revenue' => 200.00,
        'converted_at' => '2026-03-01',
    ]);

    (new SummarizeConversions($this->workspace->id, Carbon::parse('2026-03-01')))->handle();

    expect(DB::table('summary_conversion_daily')
        ->where('workspace_id', $this->workspace->id)
        ->where('summary_date', '2026-03-01')
        ->exists())->toBeTrue();

    expect(DB::table('summary_conversion_daily')
        ->where('workspace_id', $this->workspace->id)
        ->where('summary_date', '2026-02-01')
        ->exists())->toBeFalse();
});
