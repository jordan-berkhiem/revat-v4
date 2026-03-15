<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
    @foreach ($stats as $stat)
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-5 py-[18px]">
            <div class="flex justify-between items-center mb-2.5">
                <span class="text-[11.5px] font-medium uppercase tracking-[0.5px] text-slate-500 dark:text-slate-400">{{ $stat['label'] }}</span>
                <div class="w-8 h-8 rounded-lg flex items-center justify-center {{ $stat['iconBg'] }}">
                    <flux:icon :name="$stat['icon']" class="w-4 h-4 {{ $stat['iconColor'] }}" />
                </div>
            </div>
            <div class="text-[26px] font-bold font-mono tabular-nums mb-1">{{ $stat['value'] }}</div>
            @if ($stat['change'] > 0)
                <span class="text-xs font-medium text-green-600">&uarr; {{ number_format(abs($stat['change']), 1) }}%</span>
            @elseif ($stat['change'] < 0)
                <span class="text-xs font-medium text-red-600">&darr; {{ number_format(abs($stat['change']), 1) }}%</span>
            @else
                <span class="text-xs font-medium text-slate-400">0.0%</span>
            @endif
        </div>
    @endforeach
</div>
