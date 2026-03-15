<?php

namespace Database\Seeders\Test;

use App\Models\Effort;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SummarySeeder extends Seeder
{
    /**
     * Seed pre-aggregated summary tables covering the last 30 days.
     */
    public function run(): void
    {
        $workspaces = Workspace::orderBy('id')->get();

        foreach ($workspaces as $workspace) {
            $this->seedCampaignDaily($workspace);
            $this->seedCampaignByPlatform($workspace);
            $this->seedConversionDaily($workspace);
            $this->seedAttributionDaily($workspace);
            $this->seedAttributionByEffort($workspace);
            $this->seedWorkspaceDaily($workspace);

            $workspace->update(['last_summarized_at' => now()]);
        }
    }

    protected function seedCampaignDaily(Workspace $workspace): void
    {
        for ($d = 1; $d <= 30; $d++) {
            $date = now()->subDays($d)->toDateString();

            DB::table('summary_campaign_daily')->insert([
                'workspace_id' => $workspace->id,
                'summary_date' => $date,
                'campaigns_count' => fake()->numberBetween(1, 5),
                'sent' => $sent = fake()->numberBetween(500, 5000),
                'delivered' => (int) ($sent * 0.95),
                'bounced' => (int) ($sent * 0.02),
                'complaints' => fake()->numberBetween(0, 3),
                'unsubscribes' => fake()->numberBetween(0, 10),
                'opens' => fake()->numberBetween(100, (int) ($sent * 0.4)),
                'unique_opens' => fake()->numberBetween(50, (int) ($sent * 0.3)),
                'clicks' => fake()->numberBetween(10, (int) ($sent * 0.1)),
                'unique_clicks' => fake()->numberBetween(5, (int) ($sent * 0.08)),
                'platform_revenue' => fake()->randomFloat(2, 0, 500),
                'summarized_at' => now(),
            ]);
        }
    }

    protected function seedCampaignByPlatform(Workspace $workspace): void
    {
        $platforms = ['activecampaign', 'voluum'];

        for ($d = 1; $d <= 30; $d++) {
            $date = now()->subDays($d)->toDateString();

            foreach ($platforms as $platform) {
                DB::table('summary_campaign_by_platform')->insert([
                    'workspace_id' => $workspace->id,
                    'platform' => $platform,
                    'summary_date' => $date,
                    'campaigns_count' => fake()->numberBetween(1, 3),
                    'sent' => $sent = fake()->numberBetween(200, 3000),
                    'delivered' => (int) ($sent * 0.95),
                    'bounced' => (int) ($sent * 0.02),
                    'complaints' => fake()->numberBetween(0, 2),
                    'unsubscribes' => fake()->numberBetween(0, 5),
                    'opens' => fake()->numberBetween(50, (int) ($sent * 0.4)),
                    'unique_opens' => fake()->numberBetween(25, (int) ($sent * 0.3)),
                    'clicks' => fake()->numberBetween(5, (int) ($sent * 0.1)),
                    'unique_clicks' => fake()->numberBetween(3, (int) ($sent * 0.08)),
                    'platform_revenue' => fake()->randomFloat(2, 0, 250),
                    'summarized_at' => now(),
                ]);
            }
        }
    }

    protected function seedConversionDaily(Workspace $workspace): void
    {
        for ($d = 1; $d <= 30; $d++) {
            DB::table('summary_conversion_daily')->insert([
                'workspace_id' => $workspace->id,
                'summary_date' => now()->subDays($d)->toDateString(),
                'conversions_count' => fake()->numberBetween(5, 30),
                'revenue' => fake()->randomFloat(2, 100, 5000),
                'payout' => fake()->randomFloat(2, 50, 2000),
                'cost' => fake()->randomFloat(2, 10, 500),
                'summarized_at' => now(),
            ]);
        }
    }

    protected function seedAttributionDaily(Workspace $workspace): void
    {
        $models = ['first_touch', 'last_touch', 'linear'];

        for ($d = 1; $d <= 30; $d++) {
            foreach ($models as $model) {
                DB::table('summary_attribution_daily')->insert([
                    'workspace_id' => $workspace->id,
                    'summary_date' => now()->subDays($d)->toDateString(),
                    'model' => $model,
                    'attributed_conversions' => fake()->numberBetween(2, 20),
                    'attributed_revenue' => fake()->randomFloat(2, 50, 3000),
                    'total_weight' => fake()->randomFloat(4, 1, 20),
                    'summarized_at' => now(),
                ]);
            }
        }
    }

    protected function seedAttributionByEffort(Workspace $workspace): void
    {
        $efforts = Effort::where('workspace_id', $workspace->id)->get();
        $models = ['first_touch', 'last_touch', 'linear'];

        foreach ($efforts->take(5) as $effort) {
            for ($d = 1; $d <= 15; $d += 3) {
                foreach ($models as $model) {
                    DB::table('summary_attribution_by_effort')->insert([
                        'workspace_id' => $workspace->id,
                        'effort_id' => $effort->id,
                        'summary_date' => now()->subDays($d)->toDateString(),
                        'model' => $model,
                        'attributed_conversions' => fake()->numberBetween(1, 10),
                        'attributed_revenue' => fake()->randomFloat(2, 20, 1000),
                        'total_weight' => fake()->randomFloat(4, 0.5, 10),
                        'summarized_at' => now(),
                    ]);
                }
            }
        }
    }

    protected function seedWorkspaceDaily(Workspace $workspace): void
    {
        for ($d = 1; $d <= 30; $d++) {
            DB::table('summary_workspace_daily')->insert([
                'workspace_id' => $workspace->id,
                'summary_date' => now()->subDays($d)->toDateString(),
                'campaigns_count' => fake()->numberBetween(1, 5),
                'sent' => fake()->numberBetween(500, 5000),
                'opens' => fake()->numberBetween(100, 2000),
                'clicks' => fake()->numberBetween(10, 500),
                'conversions_count' => fake()->numberBetween(5, 30),
                'revenue' => fake()->randomFloat(2, 100, 5000),
                'cost' => fake()->randomFloat(2, 10, 500),
                'summarized_at' => now(),
            ]);
        }
    }
}
