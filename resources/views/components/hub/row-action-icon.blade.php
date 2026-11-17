@props([
    'icon',
    'label',
    'href' => null,
    'disabled' => false,
    'target' => null,
    'rel' => null,
    'accent' => false,
    'testId' => null,
    'tooltip' => null,
])

@php
    $tag = filled($href) && ! $disabled ? 'a' : ($attributes->has('wire:click') ? 'button' : 'span');
    $tooltipText = $tooltip ?? $label;
@endphp

<{{ $tag }}
    @if (filled($href) && ! $disabled) href="{{ $href }}" @endif
    @if ($target) target="{{ $target }}" @endif
    @if ($rel) rel="{{ $rel }}" @endif
    {{ $attributes->class([
        'hub-doc-table__action',
        'hub-doc-table__action--icon',
        'hub-doc-table__action--accent' => $accent,
        'hub-doc-table__action--disabled' => $disabled,
    ]) }}
    aria-label="{{ $label }}"
    title="{{ $tooltipText }}"
    @if ($disabled) aria-disabled="true" @endif
    @if ($testId) data-testid="{{ $testId }}" @endif
>
    @switch ($icon)
        @case('view')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            @break
        @case('download')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
            @break
        @case('print')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
            @break
        @case('resend')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
            @break
        @case('edit')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            @break
        @case('convert')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M12 18v-6"/><path d="M9 15h6"/></svg>
            @break
        @case('more')
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.75"/><circle cx="12" cy="12" r="1.75"/><circle cx="19" cy="12" r="1.75"/></svg>
            @break
    @endswitch
    <span class="sr-only">{{ $label }}</span>
</{{ $tag }}>