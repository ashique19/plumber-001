@props([
    'show' => false,
    'title' => '',
    'closeWire' => 'closeFormModal',
])

@if ($show)
    <div class="admin-hub-sheet-backdrop" wire:click.self="{{ $closeWire }}" aria-hidden="false">
        <div
            class="admin-hub-sheet"
            role="dialog"
            aria-modal="true"
            aria-labelledby="admin-hub-sheet-title"
            @click.stop
        >
            <header class="admin-hub-sheet__head sticky top-0 z-10 flex items-start justify-between gap-3 border-b border-[var(--app-border)] bg-[var(--app-surface)] pb-3">
                <h2 id="admin-hub-sheet-title" class="text-lg font-semibold leading-snug">{{ $title }}</h2>
                <button type="button" class="tap-action-muted min-h-10 min-w-10 shrink-0 rounded-xl px-2" wire:click="{{ $closeWire }}" aria-label="{{ __('catalog.cancel') }}">
                    <x-app.icon name="x" class="h-5 w-5" />
                </button>
            </header>
            <div class="admin-hub-sheet__body pt-4">
                {{ $slot }}
            </div>
        </div>
    </div>
@endif