<?php

namespace App\Jobs\Summarization;

use App\Models\Workspace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SummarizeAllWorkspaces implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public function __construct()
    {
        $this->onQueue(config('queues.summarization'));
    }

    public function handle(): void
    {
        $limit = config('summarization.workspace_limit', 20);

        $workspaces = Workspace::whereHas('integrations', function ($query) {
            $query->where('is_active', true);
        })
            ->orderBy('last_summarized_at', 'asc')
            ->limit($limit)
            ->get();

        if ($workspaces->isEmpty()) {
            return;
        }

        Log::info('SummarizeAllWorkspaces: Dispatching summarization jobs', [
            'workspace_count' => $workspaces->count(),
        ]);

        foreach ($workspaces as $workspace) {
            RunSummarization::dispatch(
                $workspace->id,
                $workspace->last_summarized_at,
            );
        }
    }
}
