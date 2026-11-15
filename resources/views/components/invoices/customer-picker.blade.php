@props([
    'results',
    'canCreate' => false,
    'compact' => false,
    'search' => '',
    'selectedCustomerId' => null,
    'showWalkInHint' => false,
])

<div class="invoice-customer-picker {{ $compact ? 'invoice-customer-picker--compact' : '' }}">
    <div class="flex gap-2">
        <div class="min-w-0 flex-1">
            <x-field.search
                :label="__('invoices.customer_search')"
                name="customerSearch"
                wire:model.live.debounce.350ms="customerSearch"
                :placeholder="__('invoices.customer_search_placeholder')"
                :clearable="trim($search) !== ''"
                clear-action="$set('customerSearch', '')"
            />
        </div>
        @if ($canCreate)
            <button
                type="button"
                class="invoice-picker-add tap-action-muted mt-7 flex min-h-12 min-w-12 shrink-0 items-center justify-center rounded-xl text-lg font-bold"
                wire:click="openQuickCustomerModal"
                aria-label="{{ __('invoices.add_customer') }}"
                title="{{ __('invoices.add_customer') }}"
            >
                +
            </button>
        @endif
    </div>

    @if (trim($search) !== '')
        <ul class="invoice-customer-picker__results" role="listbox">
            @forelse ($results as $customer)
                <li wire:key="customer-hit-{{ $customer->id }}">
                    <button
                        type="button"
                        class="invoice-customer-picker__option tap-action-muted w-full text-start"
                        wire:click="selectDocumentCustomer({{ $customer->id }})"
                        role="option"
                    >
                        <span class="block font-medium">{{ $customer->name }}</span>
                        <span class="block text-xs text-[var(--app-muted)]">
                            {{ collect([$customer->phone, $customer->vat_number, data_get($customer->profile, 'cr_number')])->filter()->implode(' · ') }}
                        </span>
                    </button>
                </li>
            @empty
                <li class="px-3 py-3 text-sm text-[var(--app-muted)]">{{ __('invoices.customer_no_match') }}</li>
            @endforelse
        </ul>
    @endif

    @if ($selectedCustomerId)
        <p class="mt-1 text-xs text-[var(--app-muted)]">{{ __('invoices.customer_linked') }}</p>
    @elseif ($showWalkInHint)
        <p class="mt-1 text-xs text-[var(--app-muted)]">{{ __('invoices.cash_customer_default') }}</p>
    @endif
</div>