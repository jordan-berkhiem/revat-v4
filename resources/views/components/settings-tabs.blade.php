@props(['active' => ''])

<flux:navbar>
    {{-- User-scoped tabs --}}
    <flux:navbar.item
        href="{{ route('settings.profile') }}"
        :current="$active === 'profile'"
    >
        Profile
    </flux:navbar.item>
    <flux:navbar.item
        href="{{ route('settings.password') }}"
        :current="$active === 'password'"
    >
        Password
    </flux:navbar.item>
    <flux:navbar.item
        href="{{ route('settings.appearance') }}"
        :current="$active === 'appearance'"
    >
        Appearance
    </flux:navbar.item>

    {{-- Org-scoped tabs (role-based visibility) --}}
    @can('manage')
        <flux:navbar.item
            href="{{ route('settings.organization') }}"
            :current="$active === 'organization'"
        >
            Organization
        </flux:navbar.item>
        <flux:navbar.item
            href="{{ route('settings.users') }}"
            :current="$active === 'users'"
        >
            Users
        </flux:navbar.item>
        <flux:navbar.item
            href="{{ route('settings.workspaces') }}"
            :current="$active === 'workspaces'"
        >
            Workspaces
        </flux:navbar.item>
    @endcan

    @can('billing')
        <flux:navbar.item
            href="{{ route('settings.support-access') }}"
            :current="$active === 'support-access'"
        >
            Support Access
        </flux:navbar.item>
    @endcan
</flux:navbar>
