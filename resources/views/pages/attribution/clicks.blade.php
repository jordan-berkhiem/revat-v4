<?php

use App\Models\CampaignEmailClick;
use App\Services\WorkspaceContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

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
    }

    #[On('date-range-changed')]
    public function onDateRangeChanged(string $start, string $end): void
    {
        $this->start = $start;
        $this->end = $end;
        $this->resetPage();
    }

    public function with(): array
    {
        $workspace = app(WorkspaceContext::class)->getWorkspace();

        if (! $workspace) {
            return ['clicks' => collect()->paginate(25)];
        }

        $query = CampaignEmailClick::query()
            ->where('campaign_email_clicks.workspace_id', $workspace->id)
            ->whereBetween('campaign_email_clicks.clicked_at', [$this->start, Carbon::parse($this->end)->endOfDay()])
            ->leftJoin('campaign_emails', 'campaign_email_clicks.campaign_email_id', '=', 'campaign_emails.id')
            ->leftJoin('identity_hashes', 'campaign_email_clicks.identity_hash_id', '=', 'identity_hashes.id')
            ->leftJoin('attribution_record_keys', function ($join) {
                $join->on('attribution_record_keys.record_id', '=', 'campaign_email_clicks.id')
                    ->where('attribution_record_keys.record_type', '=', 'campaign_email_click');
            })
            ->select(
                'campaign_email_clicks.*',
                'campaign_emails.name as campaign_name',
                'identity_hashes.hash as identity_hash',
                DB::raw('attribution_record_keys.record_id IS NOT NULL as has_attribution'),
            )
            ->orderByDesc('campaign_email_clicks.clicked_at');

        return [
            'clicks' => $query->paginate(25),
        ];
    }
}; ?>

<x-layouts.app>
    <x-slot:title>Attribution Clicks</x-slot:title>

    @volt('attribution.clicks')
    <div>
        <div class="flex justify-between items-start mb-6">
            <div>
                <h1 class="text-[22px] font-bold text-slate-900 dark:text-white">Attribution Clicks</h1>
                <p class="text-[13px] text-slate-600 dark:text-slate-300 mt-0.5">Click-level attribution data</p>
            </div>
            <flux:button href="{{ route('attribution') }}" variant="ghost" size="sm">Back to Attribution</flux:button>
        </div>

        <div class="flex flex-wrap items-center gap-3 mb-4">
            <livewire:dashboard.date-filter />
        </div>

        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
            <div class="overflow-x-auto" wire:loading.class="opacity-50">
                @if ($clicks->count() > 0)
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-700">
                                <th class="sticky left-0 bg-white dark:bg-slate-800 text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px]">Campaign</th>
                                <th class="text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px]">Identity Hash</th>
                                <th class="text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px]">Clicked At</th>
                                <th class="text-[10.5px] font-semibold uppercase tracking-[0.4px] text-slate-400 px-3 py-[11px]">Attribution Status</th>
                            </tr>
                        </thead>
                        <tbody class="text-[12.5px]">
                            @foreach ($clicks as $click)
                                @php
                                    $hash = $click->identity_hash ?? '';
                                    $maskedHash = strlen($hash) >= 8 ? substr($hash, 0, 4) . '...' . substr($hash, -4) : $hash;
                                @endphp
                                <tr class="border-b border-slate-100 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                    <td class="sticky left-0 bg-white dark:bg-slate-800 px-3 py-2.5 font-sans font-medium text-slate-800 dark:text-slate-200">{{ $click->campaign_name ?? 'Unknown' }}</td>
                                    <td class="px-3 py-2.5 font-mono text-xs text-slate-600 dark:text-slate-400">{{ $maskedHash ?: '-' }}</td>
                                    <td class="px-3 py-2.5 font-mono text-slate-500">{{ $click->clicked_at?->format('M j, Y g:ia') ?? '-' }}</td>
                                    <td class="px-3 py-2.5">
                                        @if ($click->has_attribution)
                                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-300 rounded">Linked</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-slate-100 dark:bg-slate-700 text-slate-500 rounded">Unlinked</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700">
                        {{ $clicks->links() }}
                    </div>
                @else
                    <div class="text-center py-16">
                        <flux:icon name="cursor-arrow-rays" class="w-10 h-10 text-slate-300 dark:text-slate-600 mx-auto mb-3" />
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">No clicks found</p>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Try adjusting your date range</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endvolt
</x-layouts.app>
