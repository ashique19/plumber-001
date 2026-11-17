@php
    $productTabErrors = $productFormTabErrorCounts ?? ['basic' => 0, 'pricing' => 0, 'stock' => 0];
    $productTabs = [
        'basic' => __('catalog.product_tab_basic'),
        'pricing' => __('catalog.product_tab_pricing'),
        'stock' => __('catalog.product_tab_stock'),
    ];
@endphp

<nav class="catalog-product-form-tabs app-section-tabs app-section-tabs--scroll" role="tablist" aria-label="{{ __('catalog.product_form_tabs_label') }}">
    @foreach ($productTabs as $tabKey => $tabLabel)
        <button
            type="button"
            role="tab"
            wire:click="setProductFormTab('{{ $tabKey }}')"
            aria-selected="{{ $productFormTab === $tabKey ? 'true' : 'false' }}"
            @class([
                'app-chip catalog-product-form-tabs__chip',
                'app-chip--active' => $productFormTab === $tabKey,
                'catalog-product-form-tabs__chip--error' => ($productTabErrors[$tabKey] ?? 0) > 0,
            ])
        >
            <span>{{ $tabLabel }}</span>
            @if (($productTabErrors[$tabKey] ?? 0) > 0)
                <span class="catalog-product-form-tabs__badge" aria-hidden="true">{{ $productTabErrors[$tabKey] }}</span>
            @endif
        </button>
    @endforeach
</nav>

<div class="catalog-product-form__panels min-h-0 flex-1 overflow-y-auto overscroll-contain">
    <div @class(['hidden' => $productFormTab !== 'basic'])>
        <div class="catalog-product-form__basic-layout" data-testid="catalog-product-form-basic-layout">
            <div class="catalog-product-form__basic-media">
                <x-form.product-image-upload
                    :label="__('catalog.product_image.label')"
                    wire-model="productImageUpload"
                    :preview-url="$productImageRemove ? null : $productExistingImage"
                />
            </div>
            <div class="catalog-product-form__basic-fields" data-testid="catalog-product-form-basic-fields">
                <x-field.input
                    :label="__('catalog.name_en')"
                    name="productNameEn"
                    wire:model="productNameEn"
                    required
                    hint-position="after"
                    :hint="' '"
                    wrapper-class="quick-product-modal__name-field quick-product-modal__name-field--en"
                />
                <x-field.input
                    :label="__('catalog.name_ar')"
                    :hint="__('catalog.name_ar_hint')"
                    hint-position="after"
                    name="productNameAr"
                    wire:model="productNameAr"
                    dir="rtl"
                    required
                    wrapper-class="quick-product-modal__name-field quick-product-modal__name-field--ar"
                />
                @if($catalogI18nReady ?? false)
                    <x-field.input
                        :label="__('catalog.name_bn')"
                        :hint="__('catalog.name_bn_optional_hint')"
                        name="productNameBn"
                        wire:model="productNameBn"
                    />
                @endif
                @if($catalogUnitsSizesReady ?? false)
                    <x-field.input :label="__('catalog.sku')" name="productSku" wire:model="productSku" readonly disabled />
                @else
                    <x-field.input :label="__('catalog.sku')" name="productSku" wire:model="productSku" />
                @endif
                <x-field.select :label="__('catalog.category')" name="productCategoryId" wire:model="productCategoryId" :required="($catalogUnitsSizesReady ?? false)">
                    <option value="">{{ __('catalog.none') }}</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->displayName() }}</option>
                    @endforeach
                </x-field.select>
                @if($catalogUnitsSizesReady ?? false)
                    <x-field.select :label="__('catalog.unit')" name="productUnitId" wire:model="productUnitId" :required="($catalogUnitsSizesReady ?? false)" data-testid="product-basic-unit">
                        <option value="">{{ __('catalog.none') }}</option>
                        @foreach($activeUnitsForPicker as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->displayName() }} ({{ $unit->code }})</option>
                        @endforeach
                    </x-field.select>
                    <x-field.select :label="__('catalog.size')" name="productSizeId" wire:model="productSizeId" data-testid="product-basic-size">
                        <option value="">{{ __('catalog.none') }}</option>
                        @foreach($activeSizesForPicker as $size)
                            <option value="{{ $size->id }}">{{ $size->name }}</option>
                        @endforeach
                    </x-field.select>
                @endif
                <x-field.select :label="__('catalog.item_type')" name="productItemType" wire:model="productItemType">
                    @foreach(\App\Enums\ProductItemType::cases() as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </x-field.select>
                <x-field.toggle wire:model="productActive" name="productActive" :label="__('catalog.active')" />
                @if($catalogI18nReady ?? false)
                    <x-field.textarea :label="__('catalog.description_en')" name="productDescriptionEn" wire:model="productDescriptionEn" rows="3" />
                    <x-field.textarea :label="__('catalog.description_ar')" name="productDescriptionAr" wire:model="productDescriptionAr" dir="rtl" rows="3" />
                    <x-field.textarea :label="__('catalog.description_bn')" name="productDescriptionBn" wire:model="productDescriptionBn" wrapper-class="form-field--span-2 admin-modal-span-2" rows="3" />
                @endif
                @if ($modifiersFeatureEnabled ?? false)
                    <fieldset class="catalog-product-form__modifiers" data-testid="catalog-product-modifiers">
                        <legend class="text-xs font-medium leading-snug text-[var(--app-muted)]">{{ __('catalog.modifiers') }}</legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach($modifiers as $mod)
                                <label class="app-admin-form__modifier-chip" style="border-color: var(--app-border)">
                                    <input type="checkbox" class="h-4 w-4 shrink-0" value="{{ $mod->id }}" wire:model="productModifierIds">
                                    <span class="min-w-0 break-words">{{ $mod->displayName() }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif
            </div>
        </div>
    </div>

    <div @class(['hidden' => $productFormTab !== 'pricing'])>
        <div class="admin-modal-field-grid admin-modal-field-grid--2 admin-modal-field-grid--4 catalog-product-form__tab-grid--dense">
            <x-field.input
                type="number"
                :label="__('catalog.selling_price').' ('.($priceMode === 'inc' ? __('catalog.inc_short') : __('catalog.ex_short')).')'"
                name="productPriceInput"
                wire:model="productPriceInput"
                required
                inputmode="decimal"
            />
            <x-field.input type="number" :label="__('catalog.vat_rate')" name="productVatRate" wire:model="productVatRate" inputmode="decimal" />
            @if($productStockReady ?? false)
                <x-field.input
                    type="number"
                    step="0.01"
                    min="0"
                    :label="__('catalog.purchase_price')"
                    name="productCostInput"
                    wire:model="productCostInput"
                    inputmode="decimal"
                    :required="($catalogUnitsSizesReady ?? false)"
                />
            @endif
            @if($catalogUnitsSizesReady ?? false)
                <x-field.select :label="__('catalog.vat_zatca_category')" name="productVatZatcaCategory" wire:model="productVatZatcaCategory" :required="($catalogUnitsSizesReady ?? false)">
                    @foreach($vatZatcaCategoryOptions as $option)
                        <option value="{{ $option->value }}">{{ __('catalog.vat_zatca_'.$option->value) }}</option>
                    @endforeach
                </x-field.select>
            @endif
        </div>
        <p class="mt-2 text-xs leading-snug text-[var(--app-muted)]">{{ __('catalog.price_hint') }}</p>
    </div>

    <div @class(['hidden' => $productFormTab !== 'stock']) data-testid="product-stock-tab">
        @if($productStockReady ?? false)
            <div class="admin-modal-field-grid admin-modal-field-grid--2 admin-modal-field-grid--4 catalog-product-form__tab-grid--dense">
                <x-field.toggle wire:model.live="productTrackStock" name="productTrackStock" :label="__('stock.track_stock')" wrapper-class="admin-modal-span-4" />
                @if($productTrackStock)
                    @if (! $editingProductId)
                        <x-field.input
                            type="number"
                            step="0.001"
                            min="0"
                            :label="__('catalog.opening_stock')"
                            name="productOpeningStock"
                            wire:model="productOpeningStock"
                            inputmode="decimal"
                            required
                            :hint="__('catalog.opening_stock_hint')"
                        />
                    @else
                        <x-field.input
                            type="number"
                            :label="__('catalog.current_stock')"
                            name="productCurrentStock"
                            wire:model="productCurrentStock"
                            readonly
                            disabled
                            :hint="__('catalog.current_stock_hint')"
                        />
                    @endif
                    <x-field.input
                        type="number"
                        :label="__('catalog.min_stock_level')"
                        name="productLowStockThreshold"
                        wire:model="productLowStockThreshold"
                        inputmode="decimal"
                        :hint="__('catalog.min_stock_level_hint')"
                    />
                @endif
            </div>
        @else
            <p class="text-sm text-[var(--app-muted)]">{{ __('catalog.product_stock_unavailable') }}</p>
        @endif
    </div>
</div>