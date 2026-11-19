<div class="document-create-page">
    <header class="mb-4">
        <h1 class="app-page-title">{{ __('purchases.create_title') }}</h1>
        <p class="app-page-lead">{{ __('purchases.create_subtitle') }}</p>
    </header>

    @unless($schemaReady)
        <p class="app-card p-4 text-sm text-[var(--app-muted)]">{{ __('purchases.schema_missing') }}</p>
    @else
        <form
            id="purchase-save-form"
            wire:submit="save"
            class="app-admin-form space-y-4"
            x-on:document-validation-failed.window="$nextTick(() => document.getElementById('document-validation-summary')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
            @document-line-added.window="$nextTick(() => {
                $el.closest('.document-create-page')?.querySelector('.purchase-add-line-search input[data-admin-search-input]')?.focus({ preventScroll: true });
            })"
        >
            <x-invoices.document-validation-summary />
            <x-field.section :title="__('purchases.supplier_heading')">
                <x-purchases.supplier-picker
                    :results="$supplierHits"
                    :search="$supplierSearch"
                    :selected-supplier-id="$supplierId"
                    :selected-label="$selectedSupplier?->name"
                />
                <div class="mt-3">
                    <x-field.textarea :label="__('purchases.notes')" name="notes" wire:model="notes" rows="2" :liveValidate="false" />
                </div>
            </x-field.section>

            <x-field.section :title="__('purchases.lines_heading')">
                <x-purchases.add-line-product-search
                    :results="$productHits"
                    :search="$addLineProductSearch"
                    :can-create-product="$canCreateProduct"
                />
                @foreach($lines as $index => $line)
                    @continue(! filled($line['product_id'] ?? null))
                    <div class="rounded-lg border border-[var(--app-border)] p-3" wire:key="purchase-line-{{ $index }}-{{ $line['product_id'] }}">
                        @php
                            $productLabel = $lineProducts->get($line['product_id'])?->displayName() ?? __('purchases.line_product');
                        @endphp
                        <div class="mb-2 flex items-start justify-between gap-2">
                            <p class="text-sm font-semibold">{{ $productLabel }}</p>
                            <button type="button" class="tap-action-muted text-xs text-rose-600" wire:click="removeLine({{ $index }})">{{ __('purchases.remove_line') }}</button>
                        </div>
                        <div class="admin-form-grid admin-form-grid--3 gap-3">
                            <x-field.input type="number" :label="__('purchases.quantity')" :name="'lines.'.$index.'.quantity'" wire:model="lines.{{ $index }}.quantity" required inputmode="decimal" />
                            <x-field.money :label="__('purchases.unit_cost_ex_vat')" :name="'lines.'.$index.'.unit_cost'" wire:model="lines.{{ $index }}.unit_cost" required />
                            <x-field.input type="number" :label="__('purchases.vat_rate')" :name="'lines.'.$index.'.vat_rate'" wire:model="lines.{{ $index }}.vat_rate" inputmode="decimal" />
                        </div>
                    </div>
                @endforeach
            </x-field.section>

        </form>

        <x-documents.create-footer
            :cancel-url="route('admin.stock.search')"
            :primary-label="__('purchases.submit')"
            primary-target="save"
            form-id="purchase-save-form"
            :show-draft="false"
            :show-preview="false"
        />

        <x-purchases.quick-supplier-modal :show="$showQuickSupplierModal" />
        <x-invoices.quick-product-modal
            :show="$showQuickProductModal"
            :categories="$productCategories"
            :image-preview-url="$this->quickProductImagePreviewUrl()"
        />
    @endunless
</div>