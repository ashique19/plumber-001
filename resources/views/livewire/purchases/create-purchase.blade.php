<div class="pb-24 lg:pb-8">
    <header class="mb-4">
        <h1 class="app-page-title">{{ __('purchases.create_title') }}</h1>
        <p class="app-page-lead">{{ __('purchases.create_subtitle') }}</p>
    </header>

    @unless($schemaReady)
        <p class="app-card p-4 text-sm text-[var(--app-muted)]">{{ __('purchases.schema_missing') }}</p>
    @else
        <form wire:submit="save" class="app-admin-form space-y-4">
            <section class="app-card space-y-3 p-4">
                <h2 class="text-sm font-semibold">{{ __('purchases.supplier_heading') }}</h2>
                <x-form.select :label="__('purchases.supplier')" name="supplierId" wire:model.live="supplierId">
                    <option value="">{{ __('purchases.supplier_new') }}</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </x-form.select>
                @if(!$supplierId)
                    <x-form.input :label="__('purchases.supplier_name')" name="supplierName" wire:model="supplierName" required />
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <x-form.input :label="__('purchases.supplier_vat')" name="supplierVat" wire:model="supplierVat" />
                        <x-form.input :label="__('purchases.supplier_phone')" name="supplierPhone" wire:model="supplierPhone" />
                    </div>
                @endif
                <x-form.textarea :label="__('purchases.notes')" name="notes" wire:model="notes" rows="2" />
            </section>

            <section class="app-card space-y-3 p-4">
                <h2 class="text-sm font-semibold">{{ __('purchases.lines_heading') }}</h2>
                @foreach($lines as $index => $line)
                    <div class="rounded-lg border border-[var(--app-border)] p-3" wire:key="purchase-line-{{ $index }}">
                        <x-form.select :label="__('stock.product')" :name="'lines.'.$index.'.product_id'" wire:model="lines.{{ $index }}.product_id" required>
                            <option value="">{{ __('purchases.choose_product') }}</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->displayName() }}</option>
                            @endforeach
                        </x-form.select>
                        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <x-form.input type="number" :label="__('purchases.quantity')" :name="'lines.'.$index.'.quantity'" wire:model="lines.{{ $index }}.quantity" required inputmode="decimal" />
                            <x-form.input type="number" :label="__('purchases.unit_cost_ex_vat')" :name="'lines.'.$index.'.unit_cost'" wire:model="lines.{{ $index }}.unit_cost" required inputmode="decimal" />
                            <x-form.input type="number" :label="__('purchases.vat_rate')" :name="'lines.'.$index.'.vat_rate'" wire:model="lines.{{ $index }}.vat_rate" inputmode="decimal" />
                        </div>
                        @if(count($lines) > 1)
                            <button type="button" class="tap-action-muted mt-3 text-xs" wire:click="removeLine({{ $index }})">{{ __('purchases.remove_line') }}</button>
                        @endif
                    </div>
                @endforeach
                <button type="button" class="tap-action-muted min-h-11 w-full text-sm font-semibold" wire:click="addLine">{{ __('purchases.add_line') }}</button>
            </section>

            <x-ui.livewire-action type="submit" target="save" loading="saving" class="tap-action-primary min-h-12 w-full text-base font-semibold" variant="primary">
                {{ __('purchases.submit') }}
            </x-ui.livewire-action>
        </form>
    @endunless
</div>