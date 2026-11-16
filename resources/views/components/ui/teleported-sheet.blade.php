@props([
    'show' => false,
    'closeWire',
])

@if ($show && filled($closeWire))
    @teleport('body')
        <div
            class="app-sheet-backdrop"
            wire:click="{{ $closeWire }}"
            aria-hidden="true"
        ></div>
        {{ $slot }}
    @endteleport
@endif