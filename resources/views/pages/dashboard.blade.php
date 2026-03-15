<?php

use Carbon\Carbon;
use Livewire\Volt\Component;

new class extends Component
{
    public string $timeRange = '30d';

    public string $start;

    public string $end;

    public function mount(): void
    {
        $range = session('dashboard_date_range');

        if ($range) {
            $this->start = $range['start'];
            $this->end = $range['end'];
            $this->timeRange = match ($range['preset'] ?? '') {
                'last_7' => '7d',
                'last_30' => '30d',
                'last_90' => '90d',
                default => 'custom',
            };
        } else {
            $this->setRange('30d');
        }
    }

    public function setRange(string $range): void
    {
        $this->timeRange = $range;

        [$start, $end] = match ($range) {
            '7d' => [today()->subDays(6), today()],
            '30d' => [today()->subDays(29), today()],
            '90d' => [today()->subDays(89), today()],
            default => [Carbon::parse($this->start), Carbon::parse($this->end)],
        };

        $this->start = $start->toDateString();
        $this->end = $end->toDateString();

        session(['dashboard_date_range' => [
            'start' => $this->start,
            'end' => $this->end,
            'preset' => match ($range) {
                '7d' => 'last_7',
                '30d' => 'last_30',
                '90d' => 'last_90',
                default => 'custom',
            },
        ]]);

        $this->dispatch('date-range-changed', start: $this->start, end: $this->end);
    }

    public function applyCustomRange(): void
    {
        $this->validate([
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
        ]);

        $this->timeRange = 'custom';

        session(['dashboard_date_range' => [
            'start' => $this->start,
            'end' => $this->end,
            'preset' => 'custom',
        ]]);

        $this->dispatch('date-range-changed', start: $this->start, end: $this->end);
    }
}; ?>

<x-layouts.app>
    <x-slot:title>Dashboard</x-slot:title>

    @volt('dashboard')
    <div>
        {{-- Page Header --}}
        <div class="flex justify-between items-start mb-6">
            <div>
                <h1 class="text-[22px] font-bold text-slate-900 dark:text-white">Dashboard</h1>
                <p class="text-[13px] text-slate-600 dark:text-slate-300 mt-0.5">Overview of your marketing performance</p>
            </div>

            <div class="flex items-center gap-2">
                <flux:button.group>
                    <flux:button
                        size="sm"
                        :variant="$timeRange === '7d' ? 'primary' : 'ghost'"
                        wire:click="setRange('7d')"
                        class="text-[12.5px] font-medium py-[7px] px-4"
                    >7d</flux:button>
                    <flux:button
                        size="sm"
                        :variant="$timeRange === '30d' ? 'primary' : 'ghost'"
                        wire:click="setRange('30d')"
                        class="text-[12.5px] font-medium py-[7px] px-4"
                    >30d</flux:button>
                    <flux:button
                        size="sm"
                        :variant="$timeRange === '90d' ? 'primary' : 'ghost'"
                        wire:click="setRange('90d')"
                        class="text-[12.5px] font-medium py-[7px] px-4"
                    >90d</flux:button>
                    <flux:button
                        size="sm"
                        :variant="$timeRange === 'custom' ? 'primary' : 'ghost'"
                        wire:click="$set('timeRange', 'custom')"
                        class="text-[12.5px] font-medium py-[7px] px-4"
                    >Custom</flux:button>
                </flux:button.group>

                @if ($timeRange === 'custom')
                    <div class="flex items-center gap-2 ml-2">
                        <input
                            type="date"
                            wire:model="start"
                            class="text-xs border border-slate-200 dark:border-slate-700 rounded-md px-2 py-1.5 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200"
                        />
                        <span class="text-xs text-slate-400">to</span>
                        <input
                            type="date"
                            wire:model="end"
                            class="text-xs border border-slate-200 dark:border-slate-700 rounded-md px-2 py-1.5 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200"
                        />
                        <flux:button size="sm" variant="primary" wire:click="applyCustomRange">Apply</flux:button>
                    </div>
                @endif
            </div>
        </div>

        {{-- Stat Cards --}}
        <livewire:dashboard.stat-cards />

        {{-- Charts Row --}}
        <div class="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-4 mb-6">
            {{-- Revenue Chart --}}
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5">
                <div class="flex justify-between items-center mb-5">
                    <span class="text-[15px] font-semibold text-slate-800 dark:text-slate-200">Revenue &amp; Cost</span>
                    <div class="flex gap-4">
                        <span class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                            <span class="w-2 h-2 rounded-full bg-blue-600"></span> Revenue
                        </span>
                        <span class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                            <span class="w-2 h-2 rounded-full bg-slate-400"></span> Cost
                        </span>
                    </div>
                </div>
                <livewire:dashboard.revenue-chart />
            </div>

            {{-- Campaign Performance --}}
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5">
                <div class="flex justify-between items-center mb-5">
                    <span class="text-[15px] font-semibold text-slate-800 dark:text-slate-200">Campaign Performance</span>
                </div>
                <livewire:dashboard.campaign-performance />
            </div>
        </div>

        {{-- Attribution Widget --}}
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5">
            <livewire:dashboard.attribution-widget />
        </div>
    </div>
    @endvolt
</x-layouts.app>
