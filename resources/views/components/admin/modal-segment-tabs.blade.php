@props([
    /**
     * Hide the tab bar when fewer than two tabs are visible (single-tab modals show content only).
     */
    'tabCount' => 0,
    'testId' => 'modal-segment-tabs',
])

@if ($tabCount > 1)
    <div {{ $attributes->class(['shrink-0 border-b border-[var(--app-border)] bg-white px-5 py-3']) }} data-testid="{{ $testId }}">
        <div class="app-section-tabs app-section-tabs--scroll min-w-0 max-w-full">
            {{ $slot }}
        </div>
    </div>
@endif