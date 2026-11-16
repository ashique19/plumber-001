@props([
    'label',
    'name',
    'type' => 'text',
    'hint' => null,
    'hintPosition' => null,
])

@php
    $hintPlacement = $hintPosition
        ?? $attributes->get('hint-position')
        ?? $attributes->get('hintPosition')
        ?? 'before';
@endphp

<x-form.input
    :label="$label"
    :name="$name"
    :type="$type"
    :hint="$hint"
    :hint-position="$hintPlacement"
    {{ $attributes->except(['label', 'name', 'type', 'hint', 'hintPosition', 'hint-position']) }}
/>