<?php

namespace App\Livewire\Dashboard;

use Carbon\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DateFilter extends Component
{
    public string $start;

    public string $end;

    public string $preset = 'last_30';

    #[Locked]
    public array $presets = [
        'today' => 'Today',
        'last_7' => 'Last 7 Days',
        'last_30' => 'Last 30 Days',
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'last_90' => 'Last 90 Days',
        'custom' => 'Custom',
    ];

    public function mount(): void
    {
        $saved = session('dashboard_date_range');

        if ($saved) {
            $this->start = $saved['start'];
            $this->end = $saved['end'];
            $this->preset = $saved['preset'] ?? 'custom';
        } else {
            $this->applyPreset('last_30');
        }
    }

    public function applyPreset(string $preset): void
    {
        $this->preset = $preset;

        [$start, $end] = match ($preset) {
            'today' => [today(), today()],
            'last_7' => [today()->subDays(6), today()],
            'last_30' => [today()->subDays(29), today()],
            'this_month' => [today()->startOfMonth(), today()],
            'last_month' => [today()->subMonth()->startOfMonth(), today()->subMonth()->endOfMonth()],
            'last_90' => [today()->subDays(89), today()],
            default => [Carbon::parse($this->start), Carbon::parse($this->end)],
        };

        $this->start = $start->toDateString();
        $this->end = $end->toDateString();

        $this->persistAndDispatch();
    }

    public function applyCustom(): void
    {
        $this->validate([
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
        ]);

        $this->preset = 'custom';
        $this->persistAndDispatch();
    }

    protected function persistAndDispatch(): void
    {
        session(['dashboard_date_range' => [
            'start' => $this->start,
            'end' => $this->end,
            'preset' => $this->preset,
        ]]);

        $this->dispatch('date-range-changed', start: $this->start, end: $this->end);
    }

    public function render()
    {
        return view('livewire.dashboard.date-filter');
    }
}
