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
class StatCards extends Component
{
    #[Locked]
    public array $stats = [];

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

        $this->loadStats();
    }

    #[On('date-range-changed')]
    public function onDateRangeChanged(string $start, string $end): void
    {
        $this->start = $start;
        $this->end = $end;
        $this->loadStats();
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
            @for ($i = 0; $i < 5; $i++)
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-5 py-[18px] animate-pulse">
                    <div class="h-3 bg-slate-200 dark:bg-slate-700 rounded w-20 mb-3"></div>
                    <div class="h-7 bg-slate-200 dark:bg-slate-700 rounded w-24 mb-2"></div>
                    <div class="h-3 bg-slate-200 dark:bg-slate-700 rounded w-12"></div>
                </div>
            @endfor
        </div>
        HTML;
    }

    protected function loadStats(): void
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();

        if (! $workspace) {
            $this->stats = $this->emptyStats();

            return;
        }

        $service = MetricsService::forWorkspace($workspace->id);
        $comparison = $service->getPreviousPeriodComparison(
            Carbon::parse($this->start),
            Carbon::parse($this->end),
        );

        $current = $comparison['current'];
        $changes = $comparison['changes'];

        $this->stats = [
            [
                'label' => 'Total Sent',
                'value' => $this->formatNumber($current['sent']),
                'change' => $changes['sent'],
                'icon' => 'envelope',
                'iconBg' => 'bg-blue-100 dark:bg-blue-500/15',
                'iconColor' => 'text-blue-600',
            ],
            [
                'label' => 'Open Rate',
                'value' => number_format($current['open_rate'], 1).'%',
                'change' => $changes['open_rate'],
                'icon' => 'eye',
                'iconBg' => 'bg-green-100 dark:bg-green-600/15',
                'iconColor' => 'text-green-600',
            ],
            [
                'label' => 'Click Rate',
                'value' => number_format($current['click_rate'], 1).'%',
                'change' => $changes['click_rate'],
                'icon' => 'cursor-arrow-rays',
                'iconBg' => 'bg-blue-100 dark:bg-blue-500/15',
                'iconColor' => 'text-blue-600',
            ],
            [
                'label' => 'Conversions',
                'value' => $this->formatNumber($current['conversions']),
                'change' => $changes['conversions'],
                'icon' => 'check-circle',
                'iconBg' => 'bg-green-100 dark:bg-green-600/15',
                'iconColor' => 'text-green-600',
            ],
            [
                'label' => 'Revenue',
                'value' => '$'.$this->formatNumber($current['conversion_revenue']),
                'change' => $changes['conversion_revenue'],
                'icon' => 'currency-dollar',
                'iconBg' => 'bg-green-100 dark:bg-green-600/15',
                'iconColor' => 'text-green-600',
            ],
        ];
    }

    protected function emptyStats(): array
    {
        return [
            ['label' => 'Total Sent', 'value' => '0', 'change' => 0, 'icon' => 'envelope', 'iconBg' => 'bg-blue-100 dark:bg-blue-500/15', 'iconColor' => 'text-blue-600'],
            ['label' => 'Open Rate', 'value' => '0.0%', 'change' => 0, 'icon' => 'eye', 'iconBg' => 'bg-green-100 dark:bg-green-600/15', 'iconColor' => 'text-green-600'],
            ['label' => 'Click Rate', 'value' => '0.0%', 'change' => 0, 'icon' => 'cursor-arrow-rays', 'iconBg' => 'bg-blue-100 dark:bg-blue-500/15', 'iconColor' => 'text-blue-600'],
            ['label' => 'Conversions', 'value' => '0', 'change' => 0, 'icon' => 'check-circle', 'iconBg' => 'bg-green-100 dark:bg-green-600/15', 'iconColor' => 'text-green-600'],
            ['label' => 'Revenue', 'value' => '$0', 'change' => 0, 'icon' => 'currency-dollar', 'iconBg' => 'bg-green-100 dark:bg-green-600/15', 'iconColor' => 'text-green-600'],
        ];
    }

    protected function formatNumber(float|int $value): string
    {
        if ($value >= 1000000) {
            return number_format($value / 1000000, 1).'M';
        }

        if ($value >= 1000) {
            return number_format($value / 1000, 1).'K';
        }

        return number_format($value, $value == (int) $value ? 0 : 2);
    }

    public function render()
    {
        return view('livewire.dashboard.stat-cards');
    }
}
