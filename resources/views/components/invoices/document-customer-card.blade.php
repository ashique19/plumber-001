@props([
    'customerStrip' => null,
    'results',
    'canCreate' => false,
    'search' => '',
    'selectedCustomerId' => null,
    'showWalkInHint' => false,
    'compact' => false,
])

<div class="document-create-card invoice-customer-picker">
    <div class="document-create-card__head justify-between">
        <div class="flex items-center gap-2">
            <x-app.icon name="user" class="h-5 w-5" aria-hidden="true" />
            <span>{{ __('invoices.customer_information') }}</span>
        </div>
        @if ($canCreate)
            <button type="button" class="admin-typeahead__add tap-action-outline px-3 py-1 text-xs font-semibold" wire:click="openQuickCustomerModal">
                {{ __('invoices.add_customer') }}
            </button>
        @endif
    </div>
    <div class="document-create-card__body">
        <x-invoices.customer-picker
            :results="$results"
            :can-create="false"
            :search="$search"
            :selected-customer-id="$selectedCustomerId"
            :compact="$compact"
            :show-walk-in-hint="$showWalkInHint"
        />
        @if ($customerStrip !== null)
            <div class="document-customer-strip" wire:key="customer-strip-{{ $selectedCustomerId }}-{{ md5($customerStrip['legal_name'] ?? '') }}">
                <div class="document-customer-strip__col">
                    <x-app.icon name="building" class="document-customer-strip__icon h-5 w-5" aria-hidden="true" />
                    <div class="min-w-0">
                        <p class="m-0 font-semibold">{{ $customerStrip['legal_name'] }}</p>
                        @if (filled($customerStrip['legal_name_ar'] ?? null))
                            <p class="m-0 text-[var(--app-muted)]" dir="rtl">{{ $customerStrip['legal_name_ar'] }}</p>
                        @endif
                        @if (filled($customerStrip['vat_number'] ?? null))
                            <p class="m-0 mt-1 text-xs">{{ __('business.fields.vat_number') }}: {{ $customerStrip['vat_number'] }}</p>
                        @endif
                    </div>
                </div>
                <div class="document-customer-strip__col">
                    <x-app.icon name="map-pin" class="document-customer-strip__icon h-5 w-5" aria-hidden="true" />
                    <p class="m-0">{{ $customerStrip['address_line'] !== '' ? $customerStrip['address_line'] : '—' }}</p>
                </div>
                <div class="document-customer-strip__col flex-col gap-1">
                    @if (filled($customerStrip['phone'] ?? null))
                        <p class="m-0 flex items-center gap-1"><x-app.icon name="phone" class="h-4 w-4" /> {{ $customerStrip['phone'] }}</p>
                    @endif
                    @if (filled($customerStrip['email'] ?? null))
                        <p class="m-0 flex items-center gap-1 min-w-0 break-all"><x-app.icon name="mail" class="h-4 w-4 shrink-0" /> {{ $customerStrip['email'] }}</p>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>