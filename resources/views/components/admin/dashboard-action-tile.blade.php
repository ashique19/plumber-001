@props([
    'emoji',
    'label',
    'subtitle',
    'href' => null,
    'enabled' => true,
])

@php
    $stateClass = $enabled && $href !== null
        ? 'dashboard-action-tile--live tap-action'
        : 'dashboard-action-tile--disabled pointer-events-none opacity-55';
@endphp

@if ($enabled && $href !== null)
    <a href="{{ $href }}" class="dashboard-action-tile {{ $stateClass }}">
        <span class="dashboard-action-tile__icon-circle" aria-hidden="true">{{ $emoji }}</span>
        <span class="dashboard-action-tile__label text-[var(--app-text)]">{{ $label }}</span>
        <span class="dashboard-action-tile__subtitle">{{ $subtitle }}</span>
    </a>
@else
    <div class="dashboard-action-tile {{ $stateClass }}" aria-disabled="true">
        <span class="dashboard-action-tile__icon-circle grayscale" aria-hidden="true">{{ $emoji }}</span>
        <span class="dashboard-action-tile__label text-[var(--app-text)]">{{ $label }}</span>
        <span class="dashboard-action-tile__subtitle">{{ $subtitle }}</span>
    </div>
@endif