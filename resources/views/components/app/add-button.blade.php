@props(['icon' => 'plus', 'variant' => 'default'])

@php
    $buttonClass = $variant === 'primary' ? 'tap-action-primary' : 'tap-action-add';
@endphp

<button
    type="button"
    {{ $attributes->class([
        $buttonClass,
        'mb-3 min-h-11 w-full px-4 sm:w-auto',
        'inline-flex items-center justify-center gap-2 sm:justify-center' => $variant === 'primary',
        'inline-flex items-center justify-start gap-3' => $variant !== 'primary',
    ]) }}
>
    @if ($variant === 'primary')
        <x-app.icon :name="$icon" class="h-5 w-5 shrink-0" aria-hidden="true" />
    @else
        <span class="tap-action-add__glyph" aria-hidden="true">
            <x-app.icon :name="$icon" class="h-5 w-5" />
        </span>
    @endif
    <span class="text-start leading-tight">{{ $slot }}</span>
</button>
