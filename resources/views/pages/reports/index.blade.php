<?php

use App\Services\Dashboard\MetricsService;
use App\Services\WorkspaceContext;
use Carbon\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $start;

    public string $end;

    public string $groupBy = 'day';

    public string $view = 'email';

    public string $sortField = '';

    public string $sortDirection = 'asc';

    public int $perPage = 25;

    #[Locked]
    public array $reportData = [];

    #[Locked]
    public array $totals = [];

    public function mount(): void
    {
        $range = session('dashboard_date_range', [
            'start' => today()->subDays(29)->toDateString(),
            'end' => today()->toDateString(),
        ]);

        $this->start = $range['start'];
        $this->end = $range['end'];

        $this->loadReport();
    }

    #[On('date-range-changed')]
    public function onDateRangeChanged(string $start, string $end): void
    {
        $this->start = $start;
        $this->end = $end;
        $this->resetPage();
        $this->loadReport();
    }

    public function updatedGroupBy(): void
    {
        $this->resetPage();
        $this->loadReport();
    }

    public function setView(string $view): void
    {
        $this->view = $view;
    }

    public function sort(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'desc';
        }

        $this->sortReport();
    }

    protected function loadReport(): void
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();

        if (! $workspace) {
            $this->reportData = [];
            $this->totals = [];

            return;
        }

        $service = MetricsService::forWorkspace($workspace->id);
        $result = $service->getGroupedReport(
            Carbon::parse($this->start),
            Carbon::parse($this->end),
            $this->groupBy,
        );

        $this->reportData = $result['rows'];
        $this->totals = $result['totals'];

        if ($this->sortField) {
            $this->sortReport();
        }
    }

    protected function sortReport(): void
    {
        if (! $this->sortField || empty($this->reportData)) {
            return;
        }

        $field = $this->sortField;
        $dir = $this->sortDirection;

        usort($this->reportData, function ($a, $b) use ($field, $dir) {
            $aVal = $a[$field] ?? 0;
            $bVal = $b[$field] ?? 0;

            if ($aVal == $bVal) {
                return 0;
            }

            $result = $aVal < $bVal ? -1 : 1;

            return $dir === 'desc' ? -$result : $result;
        });
    }

    public function paginatedRows(): array
    {
        $offset = ($this->getPage() - 1) * $this->perPage;

        return array_slice($this->reportData, $offset, $this->perPage);
    }

    public function totalPages(): int
    {
        return max(1, (int) ceil(count($this->reportData) / $this->perPage));
    }
}; ?>

<x-layouts.app>
    <x-slot:title>Reports</x-slot:title>

    @volt('reports.index')
    <div>
        {{-- Filter Bar --}}
        <div class="flex flex-wrap items-center gap-3 mb-4">
            <livewire:dashboard.date-filter />

            <flux:select wire:model.live="groupBy" size="sm" class="w-36">
                <flux:select.option value="day">Day</flux:select.option>
                <flux:select.option value="week">Week</flux:select.option>
                <flux:select.option value="month">Month</flux:select.option>
                <flux:select.option value="campaign">Campaign</flux:select.option>
                <flux:select.option value="platform">Platform</flux:select.option>
            </flux:select>

            <flux:button.group class="ml-auto">
                <flux:button
                    size="sm"
                    :variant="$view === 'email' ? 'primary' : 'ghost'"
                    wire:click="setView('email')"
                >Email Metrics</flux:button>
                <flux:button
                    size="sm"
                    :variant="$view === 'attribution' ? 'primary' : 'ghost'"
                    wire:click="setView('attribution')"
                >Attribution</flux:button>
            </flux:button.group>
        </div>

        {{-- Report Table --}}
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
            <div class="overflow-x-auto" wire:loading.class="opacity-50">
                @if (count($reportData) > 0)
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-700">
                                <th class="sticky left-0 bg-white dark:bg-slate-800 text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px] sticky top-0 z-10">
                                    {{ $groupBy === 'campaign' ? 'Campaign' : ($groupBy === 'platform' ? 'Platform' : 'Period') }}
                                </th>
                                @if ($view === 'email')
                                    <th class="text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px] sticky top-0 bg-white dark:bg-slate-800 cursor-pointer" wire:click="sort('sent')">Sent</th>
                                    <th class="text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px] sticky top-0 bg-white dark:bg-slate-800 cursor-pointer" wire:click="sort('opens')">Opens</th>
                                    <th class="text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px] sticky top-0 bg-white dark:bg-slate-800 cursor-pointer" wire:click="sort('open_rate')">Open Rate</th>
                                    <th class="text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px] sticky top-0 bg-white dark:bg-slate-800 cursor-pointer" wire:click="sort('clicks')">Clicks</th>
                                    <th class="text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px] sticky top-0 bg-white dark:bg-slate-800 cursor-pointer" wire:click="sort('click_rate')">Click Rate</th>
                                    <th class="text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px] sticky top-0 bg-white dark:bg-slate-800 cursor-pointer" wire:click="sort('bounced')">Bounced</th>
                                    <th class="text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px] sticky top-0 bg-white dark:bg-slate-800 cursor-pointer" wire:click="sort('unsubscribes')">Unsubs</th>
                                    <th class="text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px] sticky top-0 bg-white dark:bg-slate-800 cursor-pointer" wire:click="sort('complaints')">Complaints</th>
                                    <th class="text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px] sticky top-0 bg-white dark:bg-slate-800 cursor-pointer" wire:click="sort('conversions')">Conversions</th>
                                    <th class="text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px] sticky top-0 bg-white dark:bg-slate-800 cursor-pointer" wire:click="sort('revenue')">Revenue</th>
                                    <th class="text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px] sticky top-0 bg-white dark:bg-slate-800 cursor-pointer" wire:click="sort('cost')">Cost</th>
                                    <th class="text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px] sticky top-0 bg-white dark:bg-slate-800 cursor-pointer" wire:click="sort('profit')">Profit</th>
                                @else
                                    <th class="text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px] sticky top-0 bg-white dark:bg-slate-800 cursor-pointer" wire:click="sort('sent')">Impressions</th>
                                    <th class="text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px] sticky top-0 bg-white dark:bg-slate-800 cursor-pointer" wire:click="sort('clicks')">Clicks</th>
                                    <th class="text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px] sticky top-0 bg-white dark:bg-slate-800">CTR</th>
                                    <th class="text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px] sticky top-0 bg-white dark:bg-slate-800 cursor-pointer" wire:click="sort('conversions')">Conversions</th>
                                    <th class="text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px] sticky top-0 bg-white dark:bg-slate-800 cursor-pointer" wire:click="sort('revenue')">Revenue</th>
                                    <th class="text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px] sticky top-0 bg-white dark:bg-slate-800 cursor-pointer" wire:click="sort('cost')">Spend</th>
                                    <th class="text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px] sticky top-0 bg-white dark:bg-slate-800">ROI</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="text-[12.5px]">
                            @foreach ($this->paginatedRows() as $row)
                                <tr class="border-b border-slate-100 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                    <td class="sticky left-0 bg-white dark:bg-slate-800 px-3 py-2.5 font-sans font-medium text-slate-800 dark:text-slate-200">{{ $row['group_label'] }}</td>
                                    @if ($view === 'email')
                                        <td class="px-3 py-2.5 font-mono">{{ number_format($row['sent']) }}</td>
                                        <td class="px-3 py-2.5 font-mono">{{ number_format($row['opens']) }}</td>
                                        <td class="px-3 py-2.5 font-mono">{{ number_format($row['open_rate'], 2) }}%</td>
                                        <td class="px-3 py-2.5 font-mono">{{ number_format($row['clicks']) }}</td>
                                        <td class="px-3 py-2.5 font-mono">{{ number_format($row['click_rate'], 2) }}%</td>
                                        <td class="px-3 py-2.5 font-mono">{{ number_format($row['bounced']) }}</td>
                                        <td class="px-3 py-2.5 font-mono">{{ number_format($row['unsubscribes']) }}</td>
                                        <td class="px-3 py-2.5 font-mono">{{ number_format($row['complaints']) }}</td>
                                        <td class="px-3 py-2.5 font-mono">{{ number_format($row['conversions']) }}</td>
                                        <td class="px-3 py-2.5 font-mono">${{ number_format($row['revenue'], 2) }}</td>
                                        <td class="px-3 py-2.5 font-mono">${{ number_format($row['cost'], 2) }}</td>
                                        <td class="px-3 py-2.5 font-mono {{ $row['profit'] >= 0 ? 'text-green-600' : 'text-red-600' }} font-semibold">
                                            ${{ number_format($row['profit'], 2) }}
                                        </td>
                                    @else
                                        <td class="px-3 py-2.5 font-mono">{{ number_format($row['sent']) }}</td>
                                        <td class="px-3 py-2.5 font-mono">{{ number_format($row['clicks']) }}</td>
                                        <td class="px-3 py-2.5">
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono">{{ number_format($row['click_rate'], 2) }}%</span>
                                                <div class="w-12 h-1 bg-slate-200 dark:bg-slate-700 rounded-sm">
                                                    <div class="h-full bg-blue-600 rounded-sm" style="width: {{ min(100, $row['click_rate'] / 20 * 100) }}%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5 font-mono">{{ number_format($row['conversions']) }}</td>
                                        <td class="px-3 py-2.5 font-mono">${{ number_format($row['revenue'], 2) }}</td>
                                        <td class="px-3 py-2.5 font-mono">${{ number_format($row['cost'], 2) }}</td>
                                        @php
                                            $roi = $row['cost'] > 0 ? round(($row['revenue'] - $row['cost']) / $row['cost'] * 100, 1) : 0;
                                        @endphp
                                        <td class="px-3 py-2.5 font-mono {{ $roi >= 0 ? 'text-green-600' : 'text-red-600' }} font-semibold">
                                            {{ $roi > 0 ? '+' : '' }}{{ number_format($roi, 1) }}%
                                        </td>
                                    @endif
                                </tr>
                            @endforeach

                            {{-- Totals Row --}}
                            <tr class="bg-slate-50 dark:bg-slate-900 font-semibold border-t border-slate-200 dark:border-slate-700">
                                <td class="sticky left-0 bg-slate-50 dark:bg-slate-900 px-3 py-2.5 font-sans">Totals</td>
                                @if ($view === 'email')
                                    <td class="px-3 py-2.5 font-mono">{{ number_format($totals['sent'] ?? 0) }}</td>
                                    <td class="px-3 py-2.5 font-mono">{{ number_format($totals['opens'] ?? 0) }}</td>
                                    <td class="px-3 py-2.5 font-mono">{{ number_format($totals['open_rate'] ?? 0, 2) }}%</td>
                                    <td class="px-3 py-2.5 font-mono">{{ number_format($totals['clicks'] ?? 0) }}</td>
                                    <td class="px-3 py-2.5 font-mono">{{ number_format($totals['click_rate'] ?? 0, 2) }}%</td>
                                    <td class="px-3 py-2.5 font-mono">{{ number_format($totals['bounced'] ?? 0) }}</td>
                                    <td class="px-3 py-2.5 font-mono">{{ number_format($totals['unsubscribes'] ?? 0) }}</td>
                                    <td class="px-3 py-2.5 font-mono">{{ number_format($totals['complaints'] ?? 0) }}</td>
                                    <td class="px-3 py-2.5 font-mono">{{ number_format($totals['conversions'] ?? 0) }}</td>
                                    <td class="px-3 py-2.5 font-mono">${{ number_format($totals['revenue'] ?? 0, 2) }}</td>
                                    <td class="px-3 py-2.5 font-mono">${{ number_format($totals['cost'] ?? 0, 2) }}</td>
                                    <td class="px-3 py-2.5 font-mono {{ ($totals['profit'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        ${{ number_format($totals['profit'] ?? 0, 2) }}
                                    </td>
                                @else
                                    <td class="px-3 py-2.5 font-mono">{{ number_format($totals['sent'] ?? 0) }}</td>
                                    <td class="px-3 py-2.5 font-mono">{{ number_format($totals['clicks'] ?? 0) }}</td>
                                    <td class="px-3 py-2.5 font-mono">{{ number_format($totals['click_rate'] ?? 0, 2) }}%</td>
                                    <td class="px-3 py-2.5 font-mono">{{ number_format($totals['conversions'] ?? 0) }}</td>
                                    <td class="px-3 py-2.5 font-mono">${{ number_format($totals['revenue'] ?? 0, 2) }}</td>
                                    <td class="px-3 py-2.5 font-mono">${{ number_format($totals['cost'] ?? 0, 2) }}</td>
                                    @php
                                        $totalRoi = ($totals['cost'] ?? 0) > 0 ? round((($totals['revenue'] ?? 0) - ($totals['cost'] ?? 0)) / ($totals['cost'] ?? 1) * 100, 1) : 0;
                                    @endphp
                                    <td class="px-3 py-2.5 font-mono {{ $totalRoi >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $totalRoi > 0 ? '+' : '' }}{{ number_format($totalRoi, 1) }}%
                                    </td>
                                @endif
                            </tr>
                        </tbody>
                    </table>

                    {{-- Pagination --}}
                    @if ($this->totalPages() > 1)
                        <div class="flex justify-between items-center px-4 py-3 border-t border-slate-200 dark:border-slate-700">
                            <span class="text-xs text-slate-500">
                                Showing {{ (($this->getPage() - 1) * $perPage) + 1 }}-{{ min($this->getPage() * $perPage, count($reportData)) }} of {{ count($reportData) }} results
                            </span>
                            <div class="flex gap-1">
                                @if ($this->getPage() > 1)
                                    <flux:button size="sm" variant="ghost" wire:click="previousPage">Previous</flux:button>
                                @endif
                                @if ($this->getPage() < $this->totalPages())
                                    <flux:button size="sm" variant="ghost" wire:click="nextPage">Next</flux:button>
                                @endif
                            </div>
                        </div>
                    @endif
                @else
                    <div class="text-center py-16">
                        <flux:icon name="chart-bar" class="w-10 h-10 text-slate-300 dark:text-slate-600 mx-auto mb-3" />
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">No data for selected period</p>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Try adjusting your date range or filters</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endvolt
</x-layouts.app>
