<div class="pb-32 lg:pb-8">
    <header class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <h1 class="app-page-title">{{ $pageTitle }}</h1>
        <a href="{{ route('admin.quotations') }}" class="tap-action-muted min-h-10 px-3 text-xs">{{ __('quotations.back') }}</a>
    </header>

    <form
        wire:submit="save"
        class="app-card space-y-4 p-4"
        @document-line-added.window="$nextTick(() => document.querySelector('.invoice-document-lines__row:last-child')?.classList.add('invoice-document-lines__row--flash'))"
    >
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

        <x-invoices.document-product-lines :lines="$lines" :compact="false" />

        <section class="invoice-issue-preview-totals rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-4" aria-live="polite">
            <h2 class="mb-2 text-sm font-semibold">{{ __('invoices.preview_totals') }}</h2>
            <p class="text-2xl font-bold tabular-nums">
                {{ number_format($previewTotals['total_inc_vat'], 2) }}
                <span class="text-sm font-medium text-[var(--app-muted)]">{{ __('invoices.total_inc_vat') }}</span>
            </p>
            <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                <div class="flex justify-between gap-2">
                    <dt class="text-[var(--app-muted)]">{{ __('invoices.subtotal_ex_vat') }}</dt>
                    <dd class="font-semibold tabular-nums">{{ number_format($previewTotals['subtotal_ex_vat'], 2) }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-[var(--app-muted)]">{{ __('invoices.total_vat') }}</dt>
                    <dd class="font-semibold tabular-nums">{{ number_format($previewTotals['total_vat'], 2) }}</dd>
                </div>
            </dl>
        </section>

        <x-ui.livewire-action type="submit" target="save" loading="save" class="document-primary-submit min-h-11 w-full text-base font-semibold">
            {{ __('quotations.save') }}
        </x-ui.livewire-action>

        <x-invoices.document-sticky-action-bar
            :total="$previewTotals['total_inc_vat']"
            :submit-label="__('quotations.save')"
            wire-target="save"
        />

        <x-invoices.document-more-options
            :is-standard="$isStandard"
            :lines="$lines"
            show-quotation-fields
        />

    </form>

    <x-invoices.quick-customer-modal :show="$showQuickCustomerModal" :standard-buyer="$isStandard" />
    <x-invoices.quick-product-modal :show="$showQuickProductModal" :categories="$productCategories" />
</div>