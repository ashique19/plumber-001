@props([
    'results',
    'canCreate' => false,
    'compact' => false,
    'search' => '',
    'selectedCustomerId' => null,
    'showWalkInHint' => false,
])

<div class="invoice-customer-picker {{ $compact ? 'invoice-customer-picker--compact' : '' }}">
    <x-admin.typeahead-control
        :label="__('invoices.customer_search')"
        name="customerSearch"
        wire:model.live.debounce.350ms="customerSearch"
        :placeholder="__('invoices.customer_search_placeholder')"
        :clearable="trim($search) !== ''"
        clear-action="$set('customerSearch', '')"
        :show-add="$canCreate"
        add-action="openQuickCustomerModal"
        :add-label="__('invoices.add_customer')"
        :results-open="trim($search) !== '' && ! $selectedCustomerId"
    >
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
                                {{ \App\Support\Invoicing\InvoicingCustomerLookup::typeaheadMetaLine($customer) }}
                            </span>
                        </button>
                    </li>
                @empty
                    <li class="px-3 py-3 text-sm text-[var(--app-muted)]">{{ __('invoices.customer_no_match') }}</li>
                @endforelse
            </ul>
        @endif
    </x-admin.typeahead-control>

    @if ($selectedCustomerId)
        <p class="invoice-customer-picker__hint form-field__hint mt-1.5">{{ __('invoices.customer_linked') }}</p>
    @elseif ($showWalkInHint)
        <p class="form-field__hint mt-1.5 leading-relaxed">{{ __('invoices.cash_customer_default') }}</p>
    @endif
</div>