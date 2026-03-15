@if(auth()->user()?->isBeingImpersonated())
<div class="sticky top-0 z-50 bg-amber-500 text-amber-950 dark:bg-amber-600 dark:text-amber-50">
    <div class="mx-auto flex items-center justify-between px-4 py-2 text-sm font-medium">
        <span>
            Support Mode: {{ \App\Models\Organization::find(session('impersonating_organization_id'))?->name ?? 'Unknown' }}
        </span>
        <form method="POST" action="{{ route('support.stop-impersonation') }}">
            @csrf
            <button type="submit" class="rounded bg-amber-700 px-3 py-1 text-xs font-semibold text-white hover:bg-amber-800 dark:bg-amber-800 dark:hover:bg-amber-900">
                Exit Support Mode
            </button>
        </form>
    </div>
</div>
@endif
