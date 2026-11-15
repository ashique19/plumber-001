@props([
    'show' => false,
    'title' => '',
    'closeWire' => 'closeFormModal',
    'size' => 'lg',
])

<x-modal :show="$show" :title="$title" :close-wire="$closeWire" :size="$size" {{ $attributes }}>
    {{ $slot }}
</x-modal>