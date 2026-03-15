@props(['height' => 'h-6', 'class' => ''])
<img class="{{ $height }} w-auto block dark:hidden {{ $class }}" src="{{ asset('svg/Logo-Clear.svg') }}" alt="Revat">
<img class="{{ $height }} w-auto hidden dark:block {{ $class }}" src="{{ asset('svg/Logo-Light.svg') }}" alt="Revat">
