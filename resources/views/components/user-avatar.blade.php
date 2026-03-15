@props(['user', 'size' => 'w-[30px] h-[30px]'])

@php
$gradients = [
    'from-blue-500 to-violet-500',
    'from-emerald-500 to-teal-500',
    'from-orange-500 to-rose-500',
    'from-pink-500 to-purple-500',
    'from-cyan-500 to-blue-500',
    'from-yellow-500 to-orange-500',
];
$index = crc32($user->email ?? $user->id) % count($gradients);
$gradient = $gradients[abs($index)];
$initials = collect(explode(' ', $user->name))
    ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
    ->take(2)
    ->join('');
@endphp

<div class="{{ $size }} rounded-full bg-gradient-to-br {{ $gradient }} flex items-center justify-center flex-shrink-0">
    <span class="text-[11px] font-semibold text-white">{{ $initials }}</span>
</div>
