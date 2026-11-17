@props([
    'label',
    'name',
    'type' => 'text',
    'hint' => null,
    'hintPosition' => null,
    'wrapperClass' => '',
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
    :wrapper-class="$wrapperClass"
    {{ $attributes->except(['label', 'name', 'type', 'hint', 'hintPosition', 'hint-position', 'wrapperClass', 'wrapper-class']) }}
/>