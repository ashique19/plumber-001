<div class="pb-32 lg:pb-8">
    <header class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <h1 class="app-page-title">{{ $pageTitle }}</h1>
        <a href="{{ route('admin.quotations') }}" class="tap-action-muted min-h-10 px-3 text-xs">{{ __('quotations.back') }}</a>
    </header>

    <form
        id="quotation-save-form"
        wire:submit="save"
        class="app-card space-y-4 p-4"
        @document-line-added.window="$nextTick(() => document.querySelector('.invoice-document-lines__row:last-child')?.classList.add('invoice-document-lines__row--flash'))"
        x-on:document-validation-failed.window="$nextTick(() => document.getElementById('document-validation-summary')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
    >
        <x-invoices.document-validation-summary />
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

        <x-invoices.document-preview-totals-breakdown :preview-totals="$previewTotals" />

        <x-ui.livewire-action type="submit" wire-click="save" target="save" loading="save" class="document-primary-submit min-h-11 w-full text-base font-semibold">
            {{ __('quotations.save') }}
        </x-ui.livewire-action>

        <x-invoices.document-sticky-action-bar
            :total="$previewTotals['total_inc_vat']"
            :submit-label="__('quotations.save')"
            wire-target="save"
            wire-click="save"
            form="quotation-save-form"
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