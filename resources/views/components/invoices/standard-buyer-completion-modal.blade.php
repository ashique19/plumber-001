@props([
    'show' => false,
    'missingKeys' => [],
    'customerName' => '',
])

@php
    $showIdentity = in_array('buyer_identity', $missingKeys, true);
    $fieldNames = array_filter($missingKeys, fn (string $key): bool => ! in_array($key, ['buyer_identity'], true));
@endphp

<x-modal
    :show="$show"
    :title="__('invoices.standard_buyer_completion_title')"
    close-wire="cancelStandardBuyerCompletion"
    size="lg"
>
    <form class="x-modal-form" wire:submit.prevent="confirmStandardBuyerCompletion">
        <p class="text-sm text-[var(--app-muted)]">
            {{ __('invoices.standard_buyer_completion_intro', ['name' => $customerName]) }}
        </p>

        <div class="x-modal-form__fields x-modal-form__fields--2 mt-4">
            @if ($showIdentity)
                <x-customers.identity-fields
                    :fields="\App\Support\Customers\CustomerIdentityRules::buyerFields()"
                    more-address-prop="standardBuyerShowMoreAddress"
                    :sa-standard-buyer="true"
                />
                <p class="text-xs text-[var(--app-muted)] md:col-span-2">{{ __('invoices.buyer_id_hint') }}</p>
            @endif

            @if (in_array('buyerCountry', $missingKeys, true))
                <x-field.saudi-country-readonly wire-prop="buyerCountry" wrapper-class="md:col-span-2" />
            @endif

            @if (in_array('addrBuilding', $missingKeys, true))
                <x-field.input :label="__('business.fields.building')" name="addrBuilding" wire:model="addrBuilding" :liveValidate="false" required />
            @endif
            @if (in_array('addrStreet', $missingKeys, true))
                <x-field.input :label="__('business.fields.street')" name="addrStreet" wire:model="addrStreet" :liveValidate="false" required />
            @endif
            @if (in_array('addrDistrict', $missingKeys, true))
                <x-field.input :label="__('business.fields.district')" name="addrDistrict" wire:model="addrDistrict" :liveValidate="false" />
            @endif
            @if (in_array('addrCity', $missingKeys, true))
                <x-field.saudi-city-combobox
                    :label="__('business.fields.city')"
                    name="addrCity"
                    wire-model="addrCity"
                    required
                />
            @endif
            @if (in_array('addrPostal', $missingKeys, true))
                <x-field.input :label="__('business.fields.postal')" name="addrPostal" wire:model="addrPostal" :liveValidate="false" required />
            @endif
        </div>

        <label class="mt-4 flex min-h-12 cursor-pointer items-start gap-3 rounded-lg border border-[var(--app-border)] px-3 py-3 text-sm">
            <input
                type="checkbox"
                class="mt-0.5 h-5 w-5 shrink-0 rounded border-[var(--app-border)]"
                wire:model="standardBuyerPersistToCustomer"
            />
            <span>{{ __('invoices.save_buyer_to_customer') }}</span>
        </label>

        <div class="x-modal__footer x-modal__footer--in-form">
            <x-modal.footer
                cancel-wire="cancelStandardBuyerCompletion"
                submit-wire="confirmStandardBuyerCompletion"
                submit-target="confirmStandardBuyerCompletion"
                :submit-label="__('invoices.standard_buyer_completion_confirm')"
            />
        </div>
    </form>
</x-modal>