@props([
    'icon',
    'label',
    'href' => null,
    'comingSoon' => false,
])

@php
    $baseClass = 'dashboard-action-tile app-card flex aspect-square min-h-[7.5rem] w-full flex-col items-center justify-center gap-2 p-3 text-center transition-shadow';
    $stateClass = $comingSoon
        ? 'dashboard-action-tile--soon pointer-events-none opacity-60'
        : 'dashboard-action-tile--live hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--app-accent)]';
@endphp

@if ($comingSoon || $href === null)
    <div class="{{ $baseClass }} {{ $stateClass }}" aria-disabled="true">
        <span class="dashboard-action-tile__badge">{{ __('dashboard.tiles.coming_soon') }}</span>
        <x-app.icon :name="$icon" class="dashboard-action-tile__icon h-8 w-8 text-[var(--plumber-steel)]" />
        <span class="dashboard-action-tile__label text-sm font-semibold leading-snug text-[var(--app-text)]">{{ $label }}</span>
    </div>
@else
    <a href="{{ $href }}" class="{{ $baseClass }} {{ $stateClass }} tap-action min-h-[7.5rem]">
        <x-app.icon :name="$icon" class="dashboard-action-tile__icon h-8 w-8 text-[var(--plumber-safety)]" />
        <span class="dashboard-action-tile__label text-sm font-semibold leading-snug text-[var(--app-text)]">{{ $label }}</span>
    </a>
@endif