<div class="flex flex-wrap items-center gap-2">
    <flux:dropdown>
        <flux:button variant="ghost" size="sm" icon-trailing="chevron-down">
            {{ $presets[$preset] ?? 'Custom' }}
        </flux:button>

        <flux:menu>
            @foreach ($presets as $key => $label)
                @if ($key !== 'custom')
                    <flux:menu.item wire:click="applyPreset('{{ $key }}')">{{ $label }}</flux:menu.item>
                @endif
            @endforeach
        </flux:menu>
    </flux:dropdown>

    <div class="flex items-center gap-2">
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
        <flux:button size="sm" variant="primary" wire:click="applyCustom">Apply</flux:button>
    </div>
</div>
