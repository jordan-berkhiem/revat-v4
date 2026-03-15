<?php

namespace App\Jobs\Summarization;

use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunSummarization implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 1200; // 20 minutes

    public function __construct(
        public int $workspaceId,
        public ?Carbon $since = null,
    ) {
        $this->onQueue(config('queues.summarization'));
    }

    public function uniqueId(): string
    {
        return (string) $this->workspaceId;
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
        Log::info("RunSummarization: Starting for workspace [{$this->workspaceId}]", [
            'since' => $this->since?->toIso8601String(),
        ]);

        $workspaceId = $this->workspaceId;
        $since = $this->since;

        Bus::batch([
            new SummarizeCampaigns($workspaceId, $since),
            new SummarizeConversions($workspaceId, $since),
            new SummarizeAttribution($workspaceId, $since),
        ])
            ->name("Summarize workspace:{$workspaceId}")
            ->allowFailures()
            ->onQueue(config('queues.summarization'))
            ->then(function (Batch $batch) use ($workspaceId, $since) {
                // Dispatch workspace summary after the three parallel jobs complete
                SummarizeWorkspace::dispatch($workspaceId, $since);

                // Update last_summarized_at on the workspace
                Workspace::where('id', $workspaceId)->update([
                    'last_summarized_at' => now(),
                ]);

                Log::info("RunSummarization: Batch completed for workspace [{$workspaceId}]", [
                    'total_jobs' => $batch->totalJobs,
                    'failed_jobs' => $batch->failedJobs,
                ]);
            })
            ->catch(function (Batch $batch, Throwable $e) use ($workspaceId) {
                Log::error("RunSummarization: Batch job failed for workspace [{$workspaceId}]", [
                    'error' => $e->getMessage(),
                ]);
            })
            ->dispatch();
    }

    public function failed(Throwable $exception): void
    {
        Log::error("RunSummarization: Job failed for workspace [{$this->workspaceId}]", [
            'error' => $exception->getMessage(),
        ]);
    }
}
