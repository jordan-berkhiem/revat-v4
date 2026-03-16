<?php

namespace App\Jobs;

use App\Models\AttributionConnector;
use App\Models\Integration;
use App\Models\Workspace;
use App\Jobs\Summarization\RunSummarization;
use App\Services\AttributionEngine;
use App\Services\ConnectorKeyProcessor;
use App\Services\EffortResolver;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessAttribution implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600; // 10 minutes

    public int $uniqueFor = 900; // 15 minutes

    public function __construct(
        public Workspace $workspace,
        public ?AttributionConnector $connector = null,
        public ?string $model = null,
    ) {
        $this->onQueue(config('queues.attribution'));
    }

    /**
     * Unique ID scoped by workspace to prevent concurrent runs.
     */
    public function uniqueId(): string
    {
        return (string) $this->workspace->id;
    }

    public function handle(ConnectorKeyProcessor $keyProcessor, EffortResolver $effortResolver, AttributionEngine $engine): void
    {
        $this->linkClicksToCampaigns();

        $connectors = $this->resolveConnectors();
        $models = $this->resolveModels();

        $failedConnectors = [];
        $totalResults = 0;

        foreach ($connectors as $connector) {
            try {
                Log::info("ProcessAttribution: Starting key processing for connector [{$connector->id}] '{$connector->name}'", [
                    'workspace_id' => $this->workspace->id,
                ]);

                $keyProcessor->processKeys($connector);

                Log::info("ProcessAttribution: Key processing completed for connector [{$connector->id}]", [
                    'workspace_id' => $this->workspace->id,
                ]);

                Log::info("ProcessAttribution: Resolving efforts for connector [{$connector->id}]", [
                    'workspace_id' => $this->workspace->id,
                ]);
                $effortResolver->resolveEfforts($connector);
                Log::info("ProcessAttribution: Effort resolution completed for connector [{$connector->id}]", [
                    'workspace_id' => $this->workspace->id,
                ]);

                foreach ($models as $model) {
                    $count = $engine->run($this->workspace, $connector, $model);
                    $totalResults += $count;

                    Log::info('ProcessAttribution: Attribution run completed', [
                        'workspace_id' => $this->workspace->id,
                        'connector_id' => $connector->id,
                        'model' => $model,
                        'results_written' => $count,
                    ]);
                }
            } catch (Throwable $e) {
                $failedConnectors[] = $connector->id;

                Log::error("ProcessAttribution: Connector [{$connector->id}] failed", [
                    'workspace_id' => $this->workspace->id,
                    'connector_id' => $connector->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("ProcessAttribution: Completed for workspace [{$this->workspace->id}]", [
            'total_results' => $totalResults,
            'failed_connectors' => $failedConnectors,
        ]);

        // If ALL connectors failed, throw so the job is marked as failed
        if (count($failedConnectors) === count($connectors) && count($connectors) > 0) {
            throw new \RuntimeException(
                "ProcessAttribution: All connectors failed for workspace [{$this->workspace->id}]. "
                .'Failed connector IDs: '.implode(', ', $failedConnectors)
            );
        }

        // Mark all attributing integrations in this workspace as completed
        Integration::where('workspace_id', $this->workspace->id)
            ->where('sync_in_progress', true)
            ->where('last_sync_status', 'attributing')
            ->each(fn (Integration $i) => $i->markSyncCompleted());

        Bus::dispatch(new RunSummarization($this->workspace->id));
    }

    /**
     * Handle job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('ProcessAttribution: Job failed', [
            'workspace_id' => $this->workspace->id,
            'connector_id' => $this->connector?->id,
            'model' => $this->model,
            'error' => $exception->getMessage(),
        ]);

        // Mark all attributing integrations in this workspace as failed
        Integration::where('workspace_id', $this->workspace->id)
            ->where('sync_in_progress', true)
            ->where('last_sync_status', 'attributing')
            ->each(fn (Integration $i) => $i->markSyncFailed('Attribution processing failed.'));
    }

    /**
     * Link campaign_email_clicks to their parent campaign_emails.
     *
     * Clicks may be transformed before campaigns due to async queue processing,
     * leaving campaign_email_id NULL. This bulk update resolves those orphans
     * by matching on integration_id + external_campaign_id.
     */
    protected function linkClicksToCampaigns(): void
    {
        $updated = DB::update('
            UPDATE campaign_email_clicks cec
            JOIN campaign_email_click_raw_data cecrd ON cecrd.id = cec.raw_data_id
            JOIN campaign_emails ce
                ON ce.integration_id = cec.integration_id
                AND ce.external_id = cecrd.external_campaign_id
                AND ce.deleted_at IS NULL
            SET cec.campaign_email_id = ce.id
            WHERE cec.campaign_email_id IS NULL
              AND cec.workspace_id = ?
        ', [$this->workspace->id]);

        if ($updated > 0) {
            Log::info("ProcessAttribution: Linked {$updated} orphaned clicks to campaign emails", [
                'workspace_id' => $this->workspace->id,
            ]);
        }
    }

    /**
     * Resolve which connectors to process.
     */
    protected function resolveConnectors(): iterable
    {
        if ($this->connector) {
            return [$this->connector];
        }

        return AttributionConnector::where('workspace_id', $this->workspace->id)
            ->active()
            ->get();
    }

    /**
     * Resolve which models to run.
     */
    protected function resolveModels(): array
    {
        if ($this->model) {
            return [$this->model];
        }

        return AttributionEngine::VALID_MODELS;
    }
}
