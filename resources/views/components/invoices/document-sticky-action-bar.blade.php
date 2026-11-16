@props([
    'total' => 0,
    'submitLabel',
    'wireTarget',
    'wireClick' => null,
    'form' => null,
    'formId' => null,
])

<div class="document-sticky-action-bar lg:hidden" aria-live="polite">
    <div class="mx-auto flex max-w-lg items-center gap-3">
        <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold text-[var(--app-muted)]">{{ __('invoices.total_inc_vat') }}</p>
            <p class="text-xl font-bold tabular-nums text-[var(--app-text)]">
                {{ number_format((float) $total, 2) }}
            </p>
        </div>
        <x-ui.livewire-action
            type="submit"
            :form="$formId ?? $form"
            :wire-click="$wireClick"
            :target="$wireTarget"
            :loading="$wireTarget"
            class="tap-action-primary min-h-11 shrink-0 px-5 text-sm font-semibold"
            {{ $attributes->whereDoesntStartWith('wire:') }}
        >
            {{ $submitLabel }}
        </x-ui.livewire-action>
    </div>
</div>