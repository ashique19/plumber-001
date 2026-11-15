<div class="quotation-hub pb-24 lg:pb-8">
    <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="app-page-title">{{ __('quotations.title') }}</h1>
            <p class="app-page-lead">{{ __('quotations.subtitle') }}</p>
        </div>
        @if ($canManageQuotations ?? false)
            <a href="{{ route('admin.quotations.create') }}" class="tap-action-primary min-h-11 px-4 text-sm font-semibold">
                {{ __('quotations.create') }}
            </a>
        @endif
    </header>

    <section class="app-card app-filter-fields mb-4 grid grid-cols-1 items-end gap-3 p-4 sm:grid-cols-2">
        <x-field.input
            :label="__('quotations.search')"
            name="search"
            wire:model.live.debounce.300ms="search"
            :placeholder="__('quotations.search_hint')"
            :hint="null"
            :liveValidate="false"
            autocomplete="off"
            wrapperClass="min-w-0 sm:col-span-2"
        />
    </section>

    <section class="app-card divide-y divide-[var(--app-border)] overflow-hidden">
        @forelse ($quotations as $quotation)
            <article class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between" wire:key="quotation-{{ $quotation->id }}">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold">{{ $quotation->quotation_number }}</p>
                    <p class="text-xs text-[var(--app-muted)]">
                        {{ __('quotations.validity_until') }}: {{ $quotation->validity_until?->format('Y-m-d') }}
                        · {{ __('quotations.invoice_mode_'.$quotation->invoice_mode) }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($canManageQuotations ?? false)
                        <a class="tap-action-muted min-h-10 px-3 text-xs" href="{{ route('admin.quotations.edit', $quotation) }}">{{ __('quotations.edit') }}</a>
                        <button type="button" class="tap-action-muted min-h-10 px-3 text-xs" wire:click="convertToInvoice({{ $quotation->id }})">{{ __('quotations.convert') }}</button>
                    @endif
                    <a class="tap-action-muted min-h-10 px-3 text-xs" href="{{ route('admin.quotations.print', $quotation) }}" target="_blank" rel="noopener">{{ __('quotations.print') }}</a>
                </div>
            </article>
        @empty
            <p class="p-4 text-sm text-[var(--app-muted)]">{{ __('quotations.empty') }}</p>
        @endforelse
    </section>

    <div class="mt-4">
        {{ $quotations->links() }}
    </div>
</div>