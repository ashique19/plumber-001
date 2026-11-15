@props(['label', 'name'])

<x-form.input
    type="date"
    :label="$label"
    :name="$name"
    inputmode="none"
    autocomplete="off"
    {{ $attributes }}
/>