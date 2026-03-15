<?php

namespace App\Jobs\Summarization;

use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SummarizeWorkspace implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public bool $failOnTimeout = true;

    public function __construct(
        public int $workspaceId,
        public ?Carbon $since = null,
    ) {
        $this->onQueue(config('queues.summarization'));
    }

    /**
     * @return array<int>
     */
    public function backoff(): array
    {
        return [60];
    }

    public function handle(): void
    {
        Log::info("SummarizeWorkspace: Starting for workspace [{$this->workspaceId}]");

        // Read from summary tables (not fact tables) to avoid double-counting
        $campaignQuery = DB::table('summary_campaign_daily')
            ->select(
                'workspace_id',
                'summary_date',
                'campaigns_count',
                'sent',
                'opens',
                'clicks',
            )
            ->where('workspace_id', $this->workspaceId);

        $conversionQuery = DB::table('summary_conversion_daily')
            ->select(
                'workspace_id',
                'summary_date',
                'conversions_count',
                'revenue',
                'cost',
            )
            ->where('workspace_id', $this->workspaceId);

        // Get all unique dates from both summaries
        $campaignData = $campaignQuery->get()->keyBy('summary_date');
        $conversionData = $conversionQuery->get()->keyBy('summary_date');

        $allDates = $campaignData->keys()->merge($conversionData->keys())->unique();

        foreach ($allDates as $date) {
            $campaign = $campaignData->get($date);
            $conversion = $conversionData->get($date);

            DB::table('summary_workspace_daily')->upsert(
                [
                    'workspace_id' => $this->workspaceId,
                    'summary_date' => $date,
                    'campaigns_count' => $campaign->campaigns_count ?? 0,
                    'sent' => $campaign->sent ?? 0,
                    'opens' => $campaign->opens ?? 0,
                    'clicks' => $campaign->clicks ?? 0,
                    'conversions_count' => $conversion->conversions_count ?? 0,
                    'revenue' => $conversion->revenue ?? 0,
                    'cost' => $conversion->cost ?? 0,
                    'summarized_at' => now(),
                ],
                ['workspace_id', 'summary_date'],
                ['campaigns_count', 'sent', 'opens', 'clicks', 'conversions_count', 'revenue', 'cost', 'summarized_at'],
            );
        }

        Log::info("SummarizeWorkspace: Completed for workspace [{$this->workspaceId}], processed {$allDates->count()} dates");
    }
}
