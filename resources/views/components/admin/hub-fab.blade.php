@props([
    'label',
    'href' => null,
])

@php
    $classes = 'admin-hub-fab tap-action-primary lg:hidden';
@endphp

@if (filled($href))
    <a
        href="{{ $href }}"
        {{ $attributes->except('href')->merge(['class' => $classes]) }}
        aria-label="{{ $label }}"
        data-testid="admin-hub-fab"
    >
        <x-app.icon name="plus" class="h-6 w-6" />
        <span class="sr-only">{{ $label }}</span>
    </a>
@else
    <button
        type="button"
        {{ $attributes->merge(['class' => $classes]) }}
        aria-label="{{ $label }}"
        data-testid="admin-hub-fab"
    >
        <x-app.icon name="plus" class="h-6 w-6" />
        <span class="sr-only">{{ $label }}</span>
    </button>
@endif
