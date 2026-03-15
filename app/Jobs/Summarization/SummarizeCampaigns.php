<?php

namespace App\Jobs\Summarization;

use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SummarizeCampaigns implements ShouldQueue
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
        Log::info("SummarizeCampaigns: Starting for workspace [{$this->workspaceId}]");

        $this->summarizeCampaignDaily();
        $this->summarizeCampaignByPlatform();

        Log::info("SummarizeCampaigns: Completed for workspace [{$this->workspaceId}]");
    }

    protected function summarizeCampaignDaily(): void
    {
        $query = DB::table('campaign_emails')
            ->select(
                DB::raw('workspace_id'),
                DB::raw('DATE(sent_at) as summary_date'),
                DB::raw('COUNT(*) as campaigns_count'),
                DB::raw('COALESCE(SUM(sent), 0) as sent'),
                DB::raw('COALESCE(SUM(delivered), 0) as delivered'),
                DB::raw('COALESCE(SUM(bounced), 0) as bounced'),
                DB::raw('COALESCE(SUM(complaints), 0) as complaints'),
                DB::raw('COALESCE(SUM(unsubscribes), 0) as unsubscribes'),
                DB::raw('COALESCE(SUM(opens), 0) as opens'),
                DB::raw('COALESCE(SUM(unique_opens), 0) as unique_opens'),
                DB::raw('COALESCE(SUM(clicks), 0) as clicks'),
                DB::raw('COALESCE(SUM(unique_clicks), 0) as unique_clicks'),
                DB::raw('COALESCE(SUM(platform_revenue), 0) as platform_revenue'),
            )
            ->where('workspace_id', $this->workspaceId)
            ->whereNotNull('sent_at')
            ->whereNull('deleted_at')
            ->groupBy('workspace_id', DB::raw('DATE(sent_at)'));

        if ($this->since) {
            $query->where('updated_at', '>=', $this->since);
        }

        $rows = $query->get();

        foreach ($rows as $row) {
            DB::table('summary_campaign_daily')->upsert(
                [
                    'workspace_id' => $row->workspace_id,
                    'summary_date' => $row->summary_date,
                    'campaigns_count' => $row->campaigns_count,
                    'sent' => $row->sent,
                    'delivered' => $row->delivered,
                    'bounced' => $row->bounced,
                    'complaints' => $row->complaints,
                    'unsubscribes' => $row->unsubscribes,
                    'opens' => $row->opens,
                    'unique_opens' => $row->unique_opens,
                    'clicks' => $row->clicks,
                    'unique_clicks' => $row->unique_clicks,
                    'platform_revenue' => $row->platform_revenue,
                    'summarized_at' => now(),
                ],
                ['workspace_id', 'summary_date'],
                ['campaigns_count', 'sent', 'delivered', 'bounced', 'complaints', 'unsubscribes', 'opens', 'unique_opens', 'clicks', 'unique_clicks', 'platform_revenue', 'summarized_at'],
            );
        }
    }

    protected function summarizeCampaignByPlatform(): void
    {
        $query = DB::table('campaign_emails')
            ->join('integrations', 'campaign_emails.integration_id', '=', 'integrations.id')
            ->select(
                'campaign_emails.workspace_id',
                'integrations.platform',
                DB::raw('DATE(campaign_emails.sent_at) as summary_date'),
                DB::raw('COUNT(*) as campaigns_count'),
                DB::raw('COALESCE(SUM(campaign_emails.sent), 0) as sent'),
                DB::raw('COALESCE(SUM(campaign_emails.delivered), 0) as delivered'),
                DB::raw('COALESCE(SUM(campaign_emails.bounced), 0) as bounced'),
                DB::raw('COALESCE(SUM(campaign_emails.complaints), 0) as complaints'),
                DB::raw('COALESCE(SUM(campaign_emails.unsubscribes), 0) as unsubscribes'),
                DB::raw('COALESCE(SUM(campaign_emails.opens), 0) as opens'),
                DB::raw('COALESCE(SUM(campaign_emails.unique_opens), 0) as unique_opens'),
                DB::raw('COALESCE(SUM(campaign_emails.clicks), 0) as clicks'),
                DB::raw('COALESCE(SUM(campaign_emails.unique_clicks), 0) as unique_clicks'),
                DB::raw('COALESCE(SUM(campaign_emails.platform_revenue), 0) as platform_revenue'),
            )
            ->where('campaign_emails.workspace_id', $this->workspaceId)
            ->whereNotNull('campaign_emails.sent_at')
            ->whereNull('campaign_emails.deleted_at')
            ->groupBy('campaign_emails.workspace_id', 'integrations.platform', DB::raw('DATE(campaign_emails.sent_at)'));

        if ($this->since) {
            $query->where('campaign_emails.updated_at', '>=', $this->since);
        }

        $rows = $query->get();

        foreach ($rows as $row) {
            DB::table('summary_campaign_by_platform')->upsert(
                [
                    'workspace_id' => $row->workspace_id,
                    'platform' => $row->platform,
                    'summary_date' => $row->summary_date,
                    'campaigns_count' => $row->campaigns_count,
                    'sent' => $row->sent,
                    'delivered' => $row->delivered,
                    'bounced' => $row->bounced,
                    'complaints' => $row->complaints,
                    'unsubscribes' => $row->unsubscribes,
                    'opens' => $row->opens,
                    'unique_opens' => $row->unique_opens,
                    'clicks' => $row->clicks,
                    'unique_clicks' => $row->unique_clicks,
                    'platform_revenue' => $row->platform_revenue,
                    'summarized_at' => now(),
                ],
                ['workspace_id', 'platform', 'summary_date'],
                ['campaigns_count', 'sent', 'delivered', 'bounced', 'complaints', 'unsubscribes', 'opens', 'unique_opens', 'clicks', 'unique_clicks', 'platform_revenue', 'summarized_at'],
            );
        }
    }
}
