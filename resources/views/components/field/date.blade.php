@props(['label', 'name', 'wrapperClass' => ''])

<x-form.input
    type="date"
    :label="$label"
    :name="$name"
    inputmode="none"
    autocomplete="off"
    :wrapper-class="$wrapperClass"
    {{ $attributes->except(['label', 'name', 'wrapperClass', 'wrapper-class']) }}
/>