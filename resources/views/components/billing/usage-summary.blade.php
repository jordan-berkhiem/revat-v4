@props(['enforcementStatus'])

@php
    $items = [
        'users' => ['label' => 'Users', 'icon' => 'users'],
        'workspaces' => ['label' => 'Workspaces', 'icon' => 'building-office'],
    ];
@endphp

<div class="space-y-4">
    @foreach($items as $key => $item)
        @if(isset($enforcementStatus[$key]))
            @php
                $current = $enforcementStatus[$key]['current'];
                $max = $enforcementStatus[$key]['max'];
                $isUnlimited = $max === -1;
                $percentage = $isUnlimited ? 0 : ($max > 0 ? min(100, round(($current / $max) * 100)) : 0);
                $isNearCapacity = !$isUnlimited && $percentage >= 80;
                $isAtCapacity = !$isUnlimited && $current >= $max;
            @endphp

            <div>
                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $item['label'] }}</span>
                    <span class="{{ $isAtCapacity ? 'text-red-600 dark:text-red-400 font-semibold' : ($isNearCapacity ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-500 dark:text-zinc-400') }}">
                        {{ $current }} / {{ $isUnlimited ? '∞' : $max }}
                    </span>
                </div>

                @unless($isUnlimited)
                    <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                        <div
                            class="h-full rounded-full transition-all {{ $isAtCapacity ? 'bg-red-500' : ($isNearCapacity ? 'bg-amber-500' : 'bg-blue-500') }}"
                            style="width: {{ $percentage }}%"
                        ></div>
                    </div>
                @endunless
            </div>
        @endif
    @endforeach
</div>
