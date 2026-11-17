@props([
    'customerStrip' => null,
    'results',
    'canCreate' => false,
    'search' => '',
    'selectedCustomerId' => null,
    'showWalkInHint' => false,
    'compact' => false,
    'showWalkInContactFields' => false,
    'collapsed' => false,
])

@if ($collapsed)
    <div
        class="document-create-card document-create-card--customer document-create-card--customer-collapsed px-3 py-3 sm:px-4"
        data-testid="document-customer-collapsed"
    >
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            @if ($showWalkInHint)
                <p class="m-0 text-sm text-[var(--app-muted)]">{{ __('invoices.cash_customer_default') }}</p>
            @endif
            <button
                type="button"
                class="tap-action-outline min-h-11 w-full px-3 text-sm font-semibold sm:w-auto sm:shrink-0"
                wire:click="expandSimplifiedCustomerSection"
                data-testid="document-customer-expand"
            >
                {{ __('invoices.add_customer_optional') }}
            </button>
        </div>
    </div>
@else
    <div class="document-create-card document-create-card--customer">
        <div class="document-create-card__head justify-between">
            <div class="flex items-center gap-2">
                <x-app.icon name="user" class="h-5 w-5" aria-hidden="true" />
                <span>{{ __('invoices.customer_information') }}</span>
            </div>
            @if ($canCreate)
                <button type="button" class="tap-action-outline px-3 py-1 text-xs font-semibold" wire:click="openQuickCustomerModal">
                    {{ __('invoices.add_customer') }}
                </button>
            @endif
        </div>
        <div class="document-create-card__body">
            <x-invoices.customer-picker
                :results="$results"
                :can-create="$canCreate"
                :search="$search"
                :selected-customer-id="$selectedCustomerId"
                :compact="$compact"
                :show-walk-in-hint="$showWalkInHint"
            />
            @if ($customerStrip !== null)
                <div class="document-customer-strip" wire:key="customer-strip-{{ $selectedCustomerId }}-{{ md5($customerStrip['legal_name'] ?? '') }}">
                    <div class="document-customer-strip__actions">
                        <button
                            type="button"
                            class="document-customer-strip__clear tap-action-muted text-xs font-semibold text-[var(--app-accent)]"
                            wire:click="clearSelectedCustomer"
                            data-testid="document-customer-clear"
                        >
                            {{ __('invoices.clear_customer') }}
                        </button>
                    </div>
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
            @if ($selectedCustomerId)
                <div class="document-buyer-contact mt-3 border-t border-[var(--app-border)] pt-3">
                    <x-form.toggle
                        name="buyerContactOverride"
                        :label="__('invoices.buyer_contact_override_toggle')"
                        wire:model.live="buyerContactOverride"
                        class="document-buyer-contact__toggle"
                    />
                    <div
                        class="document-buyer-contact__fields admin-form-grid admin-form-grid--2 mt-3 gap-3"
                        wire:show="buyerContactOverride"
                        x-cloak
                    >
                            <x-field.input :label="__('invoices.buyer_phone')" name="buyerPhone" wire:model.blur="buyerPhone" inputmode="tel" autocomplete="tel" icon="phone" />
                            <x-field.input type="email" :label="__('invoices.buyer_email')" name="buyerEmail" wire:model.blur="buyerEmail" autocomplete="email" icon="mail" />
                    </div>
                </div>
            @elseif ($showWalkInContactFields)
                <div class="document-buyer-contact document-buyer-contact--walk-in mt-3 border-t border-dashed border-[var(--app-border)] pt-3">
                    <p class="m-0 mb-2 text-sm font-semibold text-[var(--app-text)]">{{ __('invoices.buyer_contact_walk_in_heading') }}</p>
                    <div class="admin-form-grid admin-form-grid--2 gap-3">
                        <x-field.input :label="__('invoices.buyer_phone')" name="buyerPhone" wire:model.blur="buyerPhone" inputmode="tel" autocomplete="tel" icon="phone" />
                        <x-field.input type="email" :label="__('invoices.buyer_email')" name="buyerEmail" wire:model.blur="buyerEmail" autocomplete="email" icon="mail" />
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif