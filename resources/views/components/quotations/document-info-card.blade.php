@props([
    'validityDays' => 0,
    'statusLabel',
])

<div class="document-create-card">
    <div class="document-create-card__head">
        <x-app.icon name="document" class="h-5 w-5" aria-hidden="true" />
        <span>{{ __('quotations.information') }}</span>
    </div>
    <div class="document-create-card__body space-y-3 text-sm">
        <div class="flex items-center justify-between gap-2">
            <span class="text-[var(--app-muted)]">{{ __('quotations.validity_period') }}</span>
            <span class="document-badge">{{ __('quotations.validity_days', ['days' => $validityDays]) }}</span>
        </div>
        <div class="flex items-center justify-between gap-2">
            <span class="text-[var(--app-muted)]">{{ __('quotations.status') }}</span>
            <span class="document-badge">{{ $statusLabel }}</span>
        </div>
    </div>
</div>