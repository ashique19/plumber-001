@props([
    'label',
    'name',
    'clearable' => false,
    'clearAction' => null,
])

<x-field.input
    type="search"
    inputmode="search"
    autocomplete="off"
    icon="search"
    :label="$label"
    :name="$name"
    :clearable="$clearable"
    :clear-action="$clearAction"
    :liveValidate="false"
    @keydown.enter.prevent
    {{ $attributes }}
/>