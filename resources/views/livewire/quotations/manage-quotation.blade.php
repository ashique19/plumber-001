<div class="pb-24 lg:pb-8">
    <header class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <h1 class="app-page-title">{{ $pageTitle }}</h1>
        <a href="{{ route('admin.quotations') }}" class="tap-action-muted min-h-10 px-3 text-xs">{{ __('quotations.back') }}</a>
    </header>

    <form wire:submit="save" class="app-card space-y-4 p-4" @unless ($isStandard) x-data="{ moreOpen: true }" @endunless>
        <div class="grid gap-3 sm:grid-cols-2">
            <x-form.select :label="__('quotations.target_invoice_mode')" name="invoiceMode" wire:model.live="invoiceMode" :liveValidate="true">
                <option value="simplified">{{ __('invoices.type.simplified') }}</option>
                <option value="standard">{{ __('invoices.type.standard') }}</option>
            </x-form.select>
            <x-form.input type="date" :label="__('quotations.validity_until')" name="validityUntil" wire:model.live="validityUntil" required />
        </div>

        @if ($isStandard)
            <fieldset class="space-y-3">
                <legend class="app-section-heading">{{ __('invoices.buyer_section') }}</legend>
                <x-invoices.customer-picker
                    :results="$customerSearchResults"
                    :can-create="$canCreateCustomers"
                    :search="$customerSearch"
                    :selected-customer-id="$selectedCustomerId"
                />
                <x-form.input :label="__('invoices.buyer_name')" name="buyerName" wire:model.blur="buyerName" required />
                <x-form.input :label="__('business.fields.vat_number')" name="buyerVat" wire:model.blur="buyerVat" />
                <x-form.input :label="__('business.fields.cr_number')" name="buyerCr" wire:model.blur="buyerCr" />
                <x-form.input :label="__('business.fields.building')" name="addrBuilding" wire:model.blur="addrBuilding" required />
                <x-form.input :label="__('business.fields.street')" name="addrStreet" wire:model.blur="addrStreet" required />
                <x-form.input :label="__('business.fields.city')" name="addrCity" wire:model.blur="addrCity" required />
                <x-form.input :label="__('business.fields.postal')" name="addrPostal" wire:model.blur="addrPostal" required />
            </fieldset>
        @else
            <x-invoices.customer-picker
                :results="$customerSearchResults"
                :can-create="$canCreateCustomers"
                :search="$customerSearch"
                :selected-customer-id="$selectedCustomerId"
                compact
            />
        @endif

        <fieldset class="space-y-3">
            <legend class="app-section-heading">{{ __('invoices.lines_section') }}</legend>
            <x-invoices.add-line-product-search
                :results="$addLineProductSearchResults"
                :can-create-product="$canCreateProducts"
                :search="$addLineProductSearch"
            />
            @foreach ($lines as $index => $line)
                <div class="app-inset-panel" wire:key="line-{{ $index }}">
                    <x-invoices.catalog-product-picker
                        :line-index="$index"
                        :products="$this->catalogProductsForLine($index)"
                        :can-create-product="$canCreateProducts"
                        :search="$catalogLineSearch[$index] ?? ''"
                        :compact="! $isStandard"
                    />
                    <x-form.input :label="__('invoices.line_name')" name="lines.{{ $index }}.name" wire:model.live="lines.{{ $index }}.name" />
                    @if ($isStandard)
                        <x-form.input class="mt-2" :label="__('invoices.line_name_ar')" name="lines.{{ $index }}.name_ar" wire:model.live="lines.{{ $index }}.name_ar" dir="rtl" />
                    @endif
                    <div class="mt-2 grid gap-2 sm:grid-cols-3">
                        <x-form.input type="number" step="0.001" min="0.001" :label="__('invoices.line_qty')" name="lines.{{ $index }}.quantity" wire:model.live="lines.{{ $index }}.quantity" />
                        <x-form.input type="number" step="0.01" :label="__('invoices.line_price_ex_vat')" name="lines.{{ $index }}.unit_price" wire:model.live="lines.{{ $index }}.unit_price" />
                        <x-form.input type="number" step="0.01" :label="__('invoices.line_vat')" name="lines.{{ $index }}.vat_rate" wire:model.live="lines.{{ $index }}.vat_rate" />
                    </div>
                    @if (count($lines) > 1)
                        <button type="button" class="tap-action-muted mt-2 min-h-10 text-xs" wire:click="removeLine({{ $index }})">{{ __('invoices.remove_line') }}</button>
                    @endif
                </div>
            @endforeach
            <button type="button" class="tap-action-muted min-h-11 w-full sm:w-auto" wire:click="addLine">{{ __('invoices.add_line') }}</button>
        </fieldset>

        @unless ($isStandard)
            <div class="space-y-3">
                <x-form.input :label="__('invoices.buyer_optional')" name="buyerNameOptional" wire:model.blur="buyerNameOptional" />
                <x-form.input :label="__('invoices.buyer_phone')" name="buyerPhone" wire:model.blur="buyerPhone" />
            </div>
        @endunless

        <section class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-4" aria-live="polite">
            <h2 class="mb-2 text-sm font-semibold">{{ __('invoices.preview_totals') }}</h2>
            <p class="text-2xl font-bold tabular-nums">{{ number_format($previewTotals['total_inc_vat'], 2) }}</p>
        </section>

        <x-ui.livewire-action type="submit" target="save" loading="save" class="tap-action-primary min-h-11 w-full sm:w-auto">
            {{ __('quotations.save') }}
        </x-ui.livewire-action>

        <x-invoices.quick-customer-modal :show="$showQuickCustomerModal" />
        <x-invoices.quick-product-modal :show="$showQuickProductModal" :categories="$productCategories" />
    </form>
</div>