<?php

namespace App\Services\Integrations;

use App\Jobs\Extraction\ExtractIntegration;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;

class SyncOrchestrator
{
    protected int $maxConcurrentPerOrg;

    public function __construct()
    {
        $this->maxConcurrentPerOrg = (int) config('integrations.max_concurrent_syncs', 3);
    }

    /**
     * Query integrations due for sync and dispatch extraction jobs,
     * enforcing per-organization concurrency limits.
     */
    public function dispatchDue(): int
    {
        $integrations = Integration::dueForSync()
            ->with('workspace.organization')
            ->get();

        if ($integrations->isEmpty()) {
            return 0;
        }

        // Count currently running syncs per organization
        $runningByOrg = Integration::where('sync_in_progress', true)
            ->with('workspace.organization')
            ->get()
            ->groupBy(fn (Integration $i) => $i->workspace?->organization_id)
            ->map->count();

        $dispatched = 0;

        foreach ($integrations as $integration) {
            $orgId = $integration->workspace?->organization_id;

            if (! $orgId) {
                continue;
            }

            $currentRunning = $runningByOrg->get($orgId, 0);

            if ($currentRunning >= $this->maxConcurrentPerOrg) {
                Log::debug("SyncOrchestrator: skipping integration {$integration->id} — org {$orgId} at concurrency limit ({$currentRunning}/{$this->maxConcurrentPerOrg})");

                continue;
            }

            ExtractIntegration::dispatch($integration);

            // Track the dispatch for subsequent iterations
            $runningByOrg[$orgId] = $currentRunning + 1;
            $dispatched++;
        }

        return $dispatched;
    }
}
