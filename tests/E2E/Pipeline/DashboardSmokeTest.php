<?php

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Dashboard\MetricsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::create(['name' => 'Dashboard Test Org']);
    $this->workspace = new Workspace(['name' => 'Default']);
    $this->workspace->organization_id = $this->org->id;
    $this->workspace->is_default = true;
    $this->workspace->save();

    $this->user = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => $this->org->id,
    ]);
    $this->org->users()->attach($this->user);
    $this->workspace->users()->attach($this->user);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
    $this->user->assignRole('owner');

    // Seed summary data for the last 7 days
    for ($d = 0; $d < 7; $d++) {
        $date = now()->subDays($d)->toDateString();

        DB::table('summary_campaign_daily')->insert([
            'workspace_id' => $this->workspace->id,
            'summary_date' => $date,
            'campaigns_count' => 2,
            'sent' => 1000,
            'delivered' => 950,
            'bounced' => 20,
            'complaints' => 1,
            'unsubscribes' => 5,
            'opens' => 300,
            'unique_opens' => 200,
            'clicks' => 50,
            'unique_clicks' => 30,
            'platform_revenue' => 100.00,
            'summarized_at' => now(),
        ]);

        DB::table('summary_conversion_daily')->insert([
            'workspace_id' => $this->workspace->id,
            'summary_date' => $date,
            'conversions_count' => 10,
            'revenue' => 500.00,
            'payout' => 200.00,
            'cost' => 50.00,
            'summarized_at' => now(),
        ]);

        foreach (['first_touch', 'last_touch', 'linear'] as $model) {
            DB::table('summary_attribution_daily')->insert([
                'workspace_id' => $this->workspace->id,
                'summary_date' => $date,
                'model' => $model,
                'attributed_conversions' => 8,
                'attributed_revenue' => 400.00,
                'total_weight' => 8.0,
                'summarized_at' => now(),
            ]);
        }

        DB::table('summary_workspace_daily')->insert([
            'workspace_id' => $this->workspace->id,
            'summary_date' => $date,
            'campaigns_count' => 2,
            'sent' => 1000,
            'opens' => 300,
            'clicks' => 50,
            'conversions_count' => 10,
            'revenue' => 500.00,
            'cost' => 50.00,
            'summarized_at' => now(),
        ]);
    }
});

it('returns correct campaign metrics from MetricsService', function () {
    $metrics = MetricsService::forWorkspace($this->workspace->id);
    $result = $metrics->getCampaignMetrics(now()->subDays(6), now());

    expect($result['campaigns'])->toBe(14); // 2 per day * 7 days
    expect($result['sent'])->toBe(7000); // 1000 per day * 7 days
    expect($result['opens'])->toBe(2100); // 300 per day * 7 days
    expect($result['clicks'])->toBe(350); // 50 per day * 7 days
    expect($result['revenue'])->toBe(700.00); // 100 per day * 7 days
    expect($result['open_rate'])->toBeGreaterThan(0);
    expect($result['click_rate'])->toBeGreaterThan(0);
});

it('returns correct conversion metrics from MetricsService', function () {
    $metrics = MetricsService::forWorkspace($this->workspace->id);
    $result = $metrics->getConversionMetrics(now()->subDays(6), now());

    expect($result['conversions'])->toBe(70); // 10 per day * 7 days
    expect($result['conversion_revenue'])->toBe(3500.00); // 500 per day * 7 days
    expect($result['cost'])->toBe(350.00); // 50 per day * 7 days
});

it('returns correct attribution summary from MetricsService', function () {
    $metrics = MetricsService::forWorkspace($this->workspace->id);
    $result = $metrics->getAttributionSummary(now()->subDays(6), now(), 'first_touch');

    expect($result['attributed_conversions'])->toBe(56); // 8 per day * 7 days
    expect($result['attributed_revenue'])->toBe(2800.00); // 400 per day * 7 days
});

it('returns daily trend data with correct date coverage', function () {
    $metrics = MetricsService::forWorkspace($this->workspace->id);
    $trend = $metrics->getDailyTrend(now()->subDays(6), now());

    expect(count($trend['dates']))->toBe(7);
    expect(array_sum($trend['sent']))->toBe(7000);
    expect(array_sum($trend['conversions']))->toBe(70);
});

it('renders dashboard page without errors when backed by summary data', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Dashboard');
});
