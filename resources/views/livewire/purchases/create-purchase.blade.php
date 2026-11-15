<div class="pb-24 lg:pb-8">
    <header class="mb-4">
        <h1 class="app-page-title">{{ __('purchases.create_title') }}</h1>
        <p class="app-page-lead">{{ __('purchases.create_subtitle') }}</p>
    </header>

    @unless($schemaReady)
        <p class="app-card p-4 text-sm text-[var(--app-muted)]">{{ __('purchases.schema_missing') }}</p>
    @else
        <form wire:submit="save" class="app-admin-form space-y-4">
            <x-field.section :title="__('purchases.supplier_heading')">
                <x-purchases.supplier-picker
                    :results="$supplierHits"
                    :search="$supplierSearch"
                    :selected-supplier-id="$supplierId"
                    :selected-label="$selectedSupplier?->name"
                />
                <x-purchases.quick-supplier-modal :show="$showQuickSupplierModal" />
                <div class="mt-3">
                    <x-field.textarea :label="__('purchases.notes')" name="notes" wire:model="notes" rows="2" />
                </div>
            </x-field.section>

            <x-field.section :title="__('purchases.lines_heading')">
                @foreach($lines as $index => $line)
                    <div class="rounded-lg border border-[var(--app-border)] p-3" wire:key="purchase-line-{{ $index }}">
                        <x-field.select :label="__('stock.product')" :name="'lines.'.$index.'.product_id'" wire:model="lines.{{ $index }}.product_id" required>
                            <option value="">{{ __('purchases.choose_product') }}</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->displayName() }}</option>
                            @endforeach
                        </x-field.select>
                        <div class="admin-form-grid admin-form-grid--3 mt-3 gap-3">
                            <x-field.input type="number" :label="__('purchases.quantity')" :name="'lines.'.$index.'.quantity'" wire:model="lines.{{ $index }}.quantity" required inputmode="decimal" />
                            <x-field.money :label="__('purchases.unit_cost_ex_vat')" :name="'lines.'.$index.'.unit_cost'" wire:model="lines.{{ $index }}.unit_cost" required />
                            <x-field.input type="number" :label="__('purchases.vat_rate')" :name="'lines.'.$index.'.vat_rate'" wire:model="lines.{{ $index }}.vat_rate" inputmode="decimal" />
                        </div>
                        @if(count($lines) > 1)
                            <button type="button" class="tap-action-muted mt-3 text-xs" wire:click="removeLine({{ $index }})">{{ __('purchases.remove_line') }}</button>
                        @endif
                    </div>
                @endforeach
                <button type="button" class="tap-action-muted min-h-11 w-full text-sm font-semibold" wire:click="addLine">{{ __('purchases.add_line') }}</button>
            </x-field.section>

            <x-field.actions>
                <x-ui.livewire-action type="submit" target="save" loading="save" class="tap-action-primary min-h-12 w-full flex-1 text-base font-semibold" variant="primary">
                    {{ __('purchases.submit') }}
                </x-ui.livewire-action>
            </x-field.actions>
        </form>
    @endunless
</div>