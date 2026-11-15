@props([
    'icon' => 'users',
    'title',
    'actionLabel' => null,
    'actionWire' => null,
])

<div class="admin-hub-empty app-card flex flex-col items-center justify-center px-6 py-16 text-center">
    <span class="admin-hub-empty__icon mb-4 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-[var(--app-accent)]/12 text-[var(--app-accent)]" aria-hidden="true">
        <x-app.icon :name="$icon" class="h-7 w-7" />
    </span>
    <p class="text-base font-semibold text-[var(--app-text)]">{{ $title }}</p>
    @if($actionLabel && $actionWire)
        <button type="button" class="tap-action-primary mt-6 min-h-12 px-6 text-sm font-semibold" wire:click="{{ $actionWire }}">
            {{ $actionLabel }}
        </button>
    @endif
    @if(trim($slot) !== '')
        <div class="mt-3 text-sm text-[var(--app-muted)]">{{ $slot }}</div>
    @endif
</div>