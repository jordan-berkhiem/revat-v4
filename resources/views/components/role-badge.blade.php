@props(['role'])

@php
$classes = match($role) {
    'owner' => 'text-blue-600 bg-blue-100 dark:bg-blue-500/15',
    'admin' => 'text-yellow-600 bg-yellow-100 dark:bg-yellow-600/15',
    'editor' => 'text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700',
    'viewer' => 'text-slate-400 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700',
    default => 'text-slate-500 bg-slate-100',
};
@endphp

<span class="inline-flex rounded-full px-2.5 py-[3px] text-[11.5px] font-[550] {{ $classes }}">
    {{ ucfirst($role) }}
</span>
