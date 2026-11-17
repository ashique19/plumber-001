@props([
    'emoji',
    'label',
    'subtitle',
    'href' => null,
    'enabled' => true,
])

@php
    $baseClass = 'dashboard-action-tile app-card flex w-full flex-col items-center justify-center gap-1 p-2.5 text-center transition-shadow';
    $stateClass = $enabled && $href !== null
        ? 'dashboard-action-tile--live hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--app-accent)]'
        : 'dashboard-action-tile--disabled pointer-events-none opacity-55';
@endphp

@if ($enabled && $href !== null)
    <a href="{{ $href }}" class="{{ $baseClass }} {{ $stateClass }} tap-action">
        <span class="dashboard-action-tile__icon-circle" aria-hidden="true">{{ $emoji }}</span>
        <span class="dashboard-action-tile__label font-semibold text-[var(--app-text)]">{{ $label }}</span>
        <span class="dashboard-action-tile__subtitle text-[var(--app-muted)]">{{ $subtitle }}</span>
    </a>
@else
    <div class="{{ $baseClass }} {{ $stateClass }}" aria-disabled="true">
        <span class="dashboard-action-tile__icon-circle grayscale" aria-hidden="true">{{ $emoji }}</span>
        <span class="dashboard-action-tile__label font-semibold text-[var(--app-text)]">{{ $label }}</span>
        <span class="dashboard-action-tile__subtitle text-[var(--app-muted)]">{{ $subtitle }}</span>
    </div>
@endif