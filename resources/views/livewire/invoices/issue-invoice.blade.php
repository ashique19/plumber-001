<div class="pb-32 lg:pb-8">
    <header class="mb-4">
        <h1 class="app-page-title">{{ $pageTitle }}</h1>
    </header>

    <section class="app-card mb-4 space-y-2 p-4">
        <p class="text-sm font-semibold">
            {{ $isStandard ? __('invoices.print_a4') : __('invoices.print_cash') }}
        </p>
        <p class="text-xs text-[var(--app-muted)]">
            {{ $isStandard ? __('invoices.layout_a4') : __('invoices.layout_cash') }}
        </p>
        <a
            class="tap-action-muted inline-flex min-h-10 items-center px-3 text-xs font-semibold"
            href="{{ route('admin.invoices.issue', ['mode' => $isStandard ? 'simplified' : 'standard']) }}"
        >
            {{ $isStandard ? __('invoices.issue_simplified_title') : __('invoices.issue_standard_title') }}
        </a>
    </section>

    <form
        id="invoice-issue-form"
        wire:submit="issue"
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

        <x-invoices.document-product-lines :lines="$lines" :compact="! $isStandard" />

        <x-invoices.document-preview-totals-breakdown :preview-totals="$previewTotals" />

        <x-invoices.zatca-not-ready-notice :show="$zatcaNotReady" />

        <x-ui.livewire-action type="submit" wire-click="issue" target="issue" loading="issue" class="document-primary-submit min-h-14 w-full text-base font-semibold">
            {{ __('invoices.issue_submit') }}
        </x-ui.livewire-action>

        <x-invoices.document-sticky-action-bar
            :total="$previewTotals['total_inc_vat']"
            :submit-label="__('invoices.issue_submit')"
            wire-target="issue"
            form="invoice-issue-form"
            wire-click="issue"
        />

        <x-invoices.document-more-options :is-standard="$isStandard" :lines="$lines" />

    </form>

    <x-invoices.quick-customer-modal
        :show="$showQuickCustomerModal"
        :standard-buyer="$isStandard || $customerQuickEditMode"
        :edit-mode="$customerQuickEditMode"
    />
    <x-invoices.standard-buyer-completion-modal
        :show="$showStandardBuyerCompletionModal"
        :missing-keys="$standardBuyerMissingKeys"
        :customer-name="$buyerName"
    />
    <x-invoices.quick-product-modal :show="$showQuickProductModal" :categories="$productCategories" />
</div>