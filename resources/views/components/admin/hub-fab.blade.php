@props([
    'label',
])

<button
    type="button"
    {{ $attributes->merge(['class' => 'admin-hub-fab tap-action-primary lg:hidden']) }}
    aria-label="{{ $label }}"
>
    <x-app.icon name="plus" class="h-6 w-6" />
    <span class="sr-only">{{ $label }}</span>
</button>