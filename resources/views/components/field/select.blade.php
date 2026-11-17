@props(['label', 'name', 'hint' => null, 'wrapperClass' => ''])

<x-form.select
    :label="$label"
    :name="$name"
    :hint="$hint"
    :wrapper-class="$wrapperClass"
    {{ $attributes->except(['label', 'name', 'hint', 'wrapperClass', 'wrapper-class']) }}
>
    {{ $slot }}
</x-form.select>