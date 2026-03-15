<div class="flex flex-col gap-[18px]">
    @forelse ($campaigns as $campaign)
        <div>
            <div class="flex justify-between mb-1.5">
                <span class="text-[13px] font-medium text-slate-800 dark:text-slate-200">{{ $campaign['name'] }}</span>
                <span class="text-xs text-slate-600 dark:text-slate-300 font-mono">{{ $campaign['formatted'] }}</span>
            </div>
            <div class="h-1.5 bg-slate-50 dark:bg-slate-900 rounded-[3px] overflow-hidden">
                <div class="h-full rounded-[3px] bg-blue-600 dark:bg-blue-500 transition-all duration-[600ms] ease-out" style="width: {{ $campaign['percent'] }}%"></div>
            </div>
        </div>
    @empty
        <div class="text-center py-8">
            <p class="text-sm text-slate-400">No campaign data available</p>
        </div>
    @endforelse
</div>
