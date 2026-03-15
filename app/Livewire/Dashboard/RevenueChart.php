<?php

namespace App\Livewire\Dashboard;

use App\Services\Dashboard\MetricsService;
use App\Services\WorkspaceContext;
use Carbon\Carbon;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

#[Lazy]
class RevenueChart extends Component
{
    #[Locked]
    public array $chartData = [];

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

        $this->loadChart();
    }

    #[On('date-range-changed')]
    public function onDateRangeChanged(string $start, string $end): void
    {
        $this->start = $start;
        $this->end = $end;
        $this->loadChart();
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 animate-pulse">
            <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-32 mb-5"></div>
            <div class="h-[220px] bg-slate-100 dark:bg-slate-700/50 rounded"></div>
        </div>
        HTML;
    }

    protected function loadChart(): void
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();

        if (! $workspace) {
            $this->chartData = ['dates' => [], 'revenue' => [], 'cost' => []];

            return;
        }

        $service = MetricsService::forWorkspace($workspace->id);
        $trend = $service->getDailyTrend(
            Carbon::parse($this->start),
            Carbon::parse($this->end),
        );

        $this->chartData = [
            'dates' => $trend['dates'],
            'revenue' => $trend['revenue'],
            'cost' => $trend['cost'],
        ];
    }

    public function render()
    {
        return view('livewire.dashboard.revenue-chart');
    }
}
