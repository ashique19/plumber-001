@props([
    'results',
    'search' => '',
    'selectedSupplierId' => null,
    'selectedLabel' => null,
])

<div class="purchase-supplier-picker">
    <x-admin.typeahead-control
        :label="__('purchases.supplier_search')"
        name="supplierSearch"
        wire:model.live.debounce.350ms="supplierSearch"
        :placeholder="__('suppliers.search_placeholder')"
        :clearable="trim($search) !== ''"
        clear-action="clearSupplierSearch"
        :show-add="true"
        add-action="openQuickSupplierModal"
        :add-label="__('suppliers.quick_add_title')"
        :results-open="trim($search) !== '' && ! $selectedSupplierId"
    >
        @if (trim($search) !== '' && ! $selectedSupplierId)
            <ul class="invoice-customer-picker__results mt-1" role="listbox">
                @forelse ($results as $supplier)
                    <li wire:key="supplier-pick-{{ $supplier->id }}">
                        <button
                            type="button"
                            class="invoice-customer-picker__option tap-action-muted w-full text-start"
                            wire:click="selectPurchaseSupplier({{ $supplier->id }})"
                            role="option"
                        >
                            <span class="font-medium">{{ $supplier->name }}</span>
                            <span class="block text-xs text-[var(--app-muted)]">{{ $supplier->displayLabel() }}</span>
                        </button>
                    </li>
                @empty
                    <li class="px-3 py-3 text-sm text-[var(--app-muted)]">{{ __('suppliers.search_empty') }}</li>
                @endforelse
            </ul>
        @endif
    </x-admin.typeahead-control>

    @if ($selectedSupplierId && $selectedLabel)
        <p class="mt-2 rounded-xl border border-[var(--app-accent)]/30 bg-[var(--app-accent)]/5 px-3 py-2 text-sm font-medium">
            {{ __('purchases.supplier_selected', ['name' => $selectedLabel]) }}
            <button type="button" class="ms-2 text-xs font-semibold text-rose-600" wire:click="clearSelectedSupplier">{{ __('payments.clear_statement') }}</button>
        </p>
    @endif
</div>