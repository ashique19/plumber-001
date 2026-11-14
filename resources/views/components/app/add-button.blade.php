@props(['icon' => 'plus', 'variant' => 'default'])

@php
    $buttonClass = $variant === 'primary' ? 'tap-action-primary' : 'tap-action-add';
@endphp

<button
    type="button"
    {{ $attributes->class([$buttonClass, 'mb-3 min-h-11 w-full gap-3 px-4 sm:w-auto', 'justify-center sm:justify-start' => $variant === 'primary']) }}
>
    <span class="tap-action-add__glyph" aria-hidden="true">
        <x-app.icon :name="$icon" class="h-5 w-5" />
    </span>
    <span class="text-start leading-tight">{{ $slot }}</span>
</button>
