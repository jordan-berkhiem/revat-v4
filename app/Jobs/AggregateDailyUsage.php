<?php

namespace App\Jobs;

use App\Models\DailyUsage;
use App\Models\Organization;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AggregateDailyUsage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @var int[]
     */
    public array $backoff = [30, 60, 120];

    public function handle(): void
    {
        $today = now()->toDateString();
        $data = [];

        $hasCampaignEmails = Schema::hasTable('campaign_emails');
        $hasConversionSales = Schema::hasTable('conversion_sales');
        $hasIntegrations = Schema::hasTable('integrations');

        Organization::with('workspaces')->cursor()->each(function (Organization $org) use ($today, &$data, $hasCampaignEmails, $hasConversionSales, $hasIntegrations) {
            foreach ($org->workspaces as $workspace) {
                $campaignsSynced = 0;
                $conversionsSynced = 0;
                $activeIntegrations = 0;

                if ($hasCampaignEmails) {
                    try {
                        $campaignsSynced = DB::table('campaign_emails')
                            ->where('workspace_id', $workspace->id)
                            ->whereNull('deleted_at')
                            ->count();
                    } catch (\Throwable) {
                        $campaignsSynced = 0;
                    }
                }

                if ($hasConversionSales) {
                    try {
                        $conversionsSynced = DB::table('conversion_sales')
                            ->where('workspace_id', $workspace->id)
                            ->whereNull('deleted_at')
                            ->count();
                    } catch (\Throwable) {
                        $conversionsSynced = 0;
                    }
                }

                if ($hasIntegrations) {
                    try {
                        $activeIntegrations = DB::table('integrations')
                            ->where('workspace_id', $workspace->id)
                            ->where('active', true)
                            ->count();
                    } catch (\Throwable) {
                        $activeIntegrations = 0;
                    }
                }

                $data[] = [
                    'organization_id' => $org->id,
                    'workspace_id' => $workspace->id,
                    'recorded_on' => $today,
                    'campaigns_synced' => $campaignsSynced,
                    'conversions_synced' => $conversionsSynced,
                    'active_integrations' => $activeIntegrations,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        });

        if (! empty($data)) {
            // Bulk upsert for performance
            DailyUsage::upsert(
                $data,
                ['workspace_id', 'recorded_on'],
                ['campaigns_synced', 'conversions_synced', 'active_integrations', 'updated_at'],
            );
        }

        Log::info('AggregateDailyUsage completed', ['records' => count($data), 'date' => $today]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('AggregateDailyUsage failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
