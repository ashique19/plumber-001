@props(['icon' => 'plus'])

<button
    type="button"
    {{ $attributes->class(['tap-action-add']) }}
>
    <span class="tap-action-add__glyph" aria-hidden="true">
        <x-app.icon :name="$icon" class="h-5 w-5" />
    </span>
    <span class="text-start leading-tight">{{ $slot }}</span>
</button>
