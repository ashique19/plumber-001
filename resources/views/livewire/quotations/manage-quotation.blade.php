<div class="pb-24 lg:pb-8">
    <header class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <h1 class="app-page-title">{{ $pageTitle }}</h1>
        <a href="{{ route('admin.quotations') }}" class="tap-action-muted min-h-10 px-3 text-xs">{{ __('quotations.back') }}</a>
    </header>

    <form wire:submit="save" class="app-card space-y-4 p-4">
        <x-invoices.customer-picker
            :results="$customerSearchResults"
            :can-create="$canCreateCustomers"
            :search="$customerSearch"
            :selected-customer-id="$selectedCustomerId"
            :compact="! $isStandard"
            :show-walk-in-hint="! $isStandard"
        />

        <x-invoices.add-line-product-search
            :results="$addLineProductSearchResults"
            :can-create-product="$canCreateProducts"
            :search="$addLineProductSearch"
        />

        <x-invoices.document-product-lines :lines="$lines" :compact="! $isStandard" />

        <section class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-4" aria-live="polite">
            <h2 class="mb-2 text-sm font-semibold">{{ __('invoices.preview_totals') }}</h2>
            <p class="text-2xl font-bold tabular-nums">{{ number_format($previewTotals['total_inc_vat'], 2) }}</p>
        </section>

        <x-ui.livewire-action type="submit" target="save" loading="save" class="tap-action-primary min-h-11 w-full sm:w-auto">
            {{ __('quotations.save') }}
        </x-ui.livewire-action>

        <x-invoices.document-more-options
            :is-standard="$isStandard"
            :lines="$lines"
            show-quotation-fields
        />

        <x-invoices.quick-customer-modal :show="$showQuickCustomerModal" :standard-buyer="$isStandard" />
        <x-invoices.quick-product-modal :show="$showQuickProductModal" :categories="$productCategories" />
    </form>
</div>