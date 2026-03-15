<?php

namespace App\Livewire\Dashboard;

use App\Services\WorkspaceContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

#[Lazy]
class CampaignPerformance extends Component
{
    #[Locked]
    public array $campaigns = [];

    public string $start;

    public string $end;

    public function mount(): void
    {
        $range = session('dashboard_date_range', [
            'start' => today()->subDays(29)->toDateString(),
            'end' => today()->toDateString(),
        ]);

        $this->start = $range['start'];
        $this->end = $range['end'];

        $this->loadCampaigns();
    }

    #[On('date-range-changed')]
    public function onDateRangeChanged(string $start, string $end): void
    {
        $this->start = $start;
        $this->end = $end;
        $this->loadCampaigns();
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 animate-pulse">
            <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-40 mb-5"></div>
            <div class="space-y-4">
                @for ($i = 0; $i < 4; $i++)
                    <div>
                        <div class="h-3 bg-slate-200 dark:bg-slate-700 rounded w-24 mb-2"></div>
                        <div class="h-1.5 bg-slate-100 dark:bg-slate-700 rounded"></div>
                    </div>
                @endfor
            </div>
        </div>
        HTML;
    }

    protected function loadCampaigns(): void
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();

        if (! $workspace) {
            $this->campaigns = [];

            return;
        }

        $rows = DB::table('campaign_emails')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('sent_at', [$this->start, Carbon::parse($this->end)->endOfDay()])
            ->whereNull('deleted_at')
            ->select('name', DB::raw('COALESCE(sent, 0) as sent'))
            ->orderByDesc('sent')
            ->limit(5)
            ->get();

        $maxSent = $rows->max('sent') ?: 1;

        $this->campaigns = $rows->map(fn ($row) => [
            'name' => $row->name ?? 'Untitled',
            'sent' => (int) $row->sent,
            'formatted' => $this->formatNumber((int) $row->sent).' sent',
            'percent' => round(($row->sent / $maxSent) * 100),
        ])->all();
    }

    protected function formatNumber(int $value): string
    {
        if ($value >= 1000000) {
            return number_format($value / 1000000, 1).'M';
        }

        if ($value >= 1000) {
            return number_format($value / 1000, 1).'k';
        }

        return (string) $value;
    }

    public function render()
    {
        return view('livewire.dashboard.campaign-performance');
    }
}
