@props(['label', 'name', 'hint' => null, 'wrapperClass' => ''])

<x-form.textarea
    :label="$label"
    :name="$name"
    :hint="$hint"
    :wrapper-class="$wrapperClass"
    {{ $attributes->except(['label', 'name', 'hint', 'wrapperClass', 'wrapper-class']) }}
>
    {{ $slot }}
</x-form.textarea>