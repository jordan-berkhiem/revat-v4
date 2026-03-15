<?php

namespace App\Jobs\Summarization;

use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SummarizeConversions implements ShouldQueue
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
        Log::info("SummarizeConversions: Starting for workspace [{$this->workspaceId}]");

        $query = DB::table('conversion_sales')
            ->select(
                DB::raw('workspace_id'),
                DB::raw('DATE(converted_at) as summary_date'),
                DB::raw('COUNT(*) as conversions_count'),
                DB::raw('COALESCE(SUM(revenue), 0) as revenue'),
                DB::raw('COALESCE(SUM(payout), 0) as payout'),
                DB::raw('COALESCE(SUM(cost), 0) as cost'),
            )
            ->where('workspace_id', $this->workspaceId)
            ->whereNotNull('converted_at')
            ->whereNull('deleted_at')
            ->groupBy('workspace_id', DB::raw('DATE(converted_at)'));

        if ($this->since) {
            $query->where('updated_at', '>=', $this->since);
        }

        $rows = $query->get();

        foreach ($rows as $row) {
            DB::table('summary_conversion_daily')->upsert(
                [
                    'workspace_id' => $row->workspace_id,
                    'summary_date' => $row->summary_date,
                    'conversions_count' => $row->conversions_count,
                    'revenue' => $row->revenue,
                    'payout' => $row->payout,
                    'cost' => $row->cost,
                    'summarized_at' => now(),
                ],
                ['workspace_id', 'summary_date'],
                ['conversions_count', 'revenue', 'payout', 'cost', 'summarized_at'],
            );
        }

        Log::info("SummarizeConversions: Completed for workspace [{$this->workspaceId}]");
    }
}
