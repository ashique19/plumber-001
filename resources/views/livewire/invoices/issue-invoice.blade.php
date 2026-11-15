<div class="pb-24 lg:pb-8">
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

    <form wire:submit="issue" class="app-card space-y-4 p-4">
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
                @if (($previewTotals['retention_amount'] ?? 0) > 0)
                    <div class="flex justify-between gap-2 sm:col-span-2">
                        <dt class="text-[var(--app-muted)]">{{ __('invoices.retention_amount') }}</dt>
                        <dd class="font-semibold tabular-nums">{{ number_format($previewTotals['retention_amount'], 2) }}</dd>
                    </div>
                @endif
            </dl>
        </section>

        <x-invoices.zatca-not-ready-notice :show="$zatcaNotReady" />

        <x-ui.livewire-action type="submit" target="issue" loading="issue" class="tap-action-primary min-h-14 w-full text-base font-semibold">
            {{ __('invoices.issue_submit') }}
        </x-ui.livewire-action>

        <x-invoices.document-more-options :is-standard="$isStandard" :lines="$lines" />

        <x-invoices.quick-customer-modal :show="$showQuickCustomerModal" :standard-buyer="$isStandard" />
        <x-invoices.quick-product-modal :show="$showQuickProductModal" :categories="$productCategories" />
    </form>
</div>