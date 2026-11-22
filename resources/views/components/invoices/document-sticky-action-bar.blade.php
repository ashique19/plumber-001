@props([
    'total' => 0,
    'submitLabel',
    'wireTarget',
    'wireClick' => null,
    'form' => null,
    'formId' => null,
    'printTarget' => null,
    'printLabel' => null,
    'printShortLabel' => null,
])

<div class="document-sticky-action-bar lg:hidden" aria-live="polite">
    <div class="mx-auto flex max-w-lg flex-wrap items-center gap-2 sm:gap-3">
        <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold text-[var(--app-muted)]">{{ __('invoices.total_inc_vat') }}</p>
            <p class="text-xl font-bold tabular-nums text-[var(--app-text)]">
                {{ number_format((float) $total, 2) }}
            </p>
        </div>
        <div class="flex min-w-0 flex-wrap justify-end gap-2">
            @if (filled($printTarget))
                <x-ui.livewire-action
                    type="button"
                    wire-click="{{ $printTarget }}"
                    :target="$printTarget"
                    :loading="$printTarget"
                    class="tap-action-primary min-h-11 shrink-0 px-3 text-sm font-semibold"
                    data-testid="issue-and-print-mobile"
                >
                    <span class="document-create-footer__label-full">{{ $printLabel ?? __('invoices.issue_and_print') }}</span>
                    <span class="document-create-footer__label-short">{{ $printShortLabel ?? __('invoices.issue_and_print_short') }}</span>
                </x-ui.livewire-action>
            @endif
            <x-ui.livewire-action
                type="submit"
                :form="$formId ?? $form"
                :wire-click="$wireClick"
                :target="$wireTarget"
                :loading="$wireTarget"
                class="tap-action-primary min-h-11 shrink-0 px-3 text-sm font-semibold sm:px-5"
                {{ $attributes->whereDoesntStartWith('wire:') }}
            >
                @if (filled($printTarget))
                    <span class="document-create-footer__label-full">{{ $submitLabel }}</span>
                    <span class="document-create-footer__label-short">{{ __('invoices.issue_submit_short') }}</span>
                @else
                    {{ $submitLabel }}
                @endif
            </x-ui.livewire-action>
        </div>
    </div>
</div>