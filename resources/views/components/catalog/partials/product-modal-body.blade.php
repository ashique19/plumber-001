@php
    $productTabs = [
        'basic' => __('catalog.product_tab_basic'),
        'details' => __('catalog.product_tab_details'),
    ];
    $descriptionSpan = 'form-field--span-2 admin-modal-span-2 catalog-product-form__description-field';
    $liveValidate = $liveValidate ?? ($variant === 'catalog');
    $priceMode = $priceMode ?? 'ex';
@endphp

<nav class="catalog-product-form-tabs app-section-tabs app-section-tabs--scroll" role="tablist" aria-label="{{ __('catalog.product_form_tabs_label') }}">
    @foreach ($productTabs as $tabKey => $tabLabel)
        <button
            type="button"
            role="tab"
            wire:click="{{ $setTabMethod }}('{{ $tabKey }}')"
            aria-selected="{{ $formTab === $tabKey ? 'true' : 'false' }}"
            @class([
                'app-chip catalog-product-form-tabs__chip',
                'app-chip--active' => $formTab === $tabKey,
                'catalog-product-form-tabs__chip--error' => ($tabErrorCounts[$tabKey] ?? 0) > 0,
            ])
        >
            <span>{{ $tabLabel }}</span>
            @if (($tabErrorCounts[$tabKey] ?? 0) > 0)
                <span class="catalog-product-form-tabs__badge" aria-hidden="true">{{ $tabErrorCounts[$tabKey] }}</span>
            @endif
        </button>
    @endforeach
</nav>

<div class="catalog-product-form__panels min-h-0 flex-1 overflow-y-auto overscroll-contain">
    <div @class(['hidden' => $formTab !== 'basic']) data-testid="catalog-product-form-basic-panel">
        <div class="catalog-product-form__basic-layout" data-testid="catalog-product-form-basic-layout">
            <div class="catalog-product-form__basic-media">
                <x-form.product-image-upload
                    :label="__('catalog.product_image.label')"
                    :wire-model="$imageUploadModel"
                    :preview-url="$imagePreviewUrl"
                    :remove-action="$imageRemoveAction"
                />
            </div>
            <div class="catalog-product-form__basic-fields" data-testid="catalog-product-form-basic-fields">
                <x-field.input
                    :label="__('catalog.name_en')"
                    :name="$nameEn"
                    :wire:model="$nameEn"
                    :liveValidate="$liveValidate"
                    required
                    hint-position="after"
                    :hint="' '"
                    wrapper-class="catalog-product-form__aligned-field catalog-product-form__aligned-field--spacer-hint"
                />
                <x-field.input
                    :label="__('catalog.name_ar')"
                    :hint="__('catalog.name_ar_hint')"
                    hint-position="after"
                    :name="$nameAr"
                    :wire:model="$nameAr"
                    :liveValidate="$liveValidate"
                    required
                    dir="rtl"
                    wrapper-class="catalog-product-form__aligned-field"
                />
                @if($catalogI18nReady ?? false)
                    <x-field.input
                        :label="__('catalog.name_bn')"
                        :hint="__('catalog.name_bn_optional_hint')"
                        hint-position="after"
                        :name="$nameBn"
                        :wire:model="$nameBn"
                        :liveValidate="$liveValidate"
                        wrapper-class="catalog-product-form__aligned-field catalog-product-form__aligned-field--spacer-hint admin-modal-span-2 form-field--span-2"
                    />
                @endif
                <x-field.input
                    type="number"
                    :label="__('catalog.selling_price').' ('.($priceMode === 'inc' ? __('catalog.inc_short') : __('catalog.ex_short')).')'"
                    :name="$priceModel"
                    :wire:model="$priceModel"
                    :liveValidate="$liveValidate"
                    required
                    inputmode="decimal"
                    hint-position="after"
                    :hint="' '"
                    wrapper-class="catalog-product-form__aligned-field catalog-product-form__aligned-field--spacer-hint"
                />
                <x-field.input
                    type="number"
                    :label="__('catalog.vat_rate')"
                    :name="$vatModel"
                    :wire:model="$vatModel"
                    :liveValidate="$liveValidate"
                    inputmode="decimal"
                    step="0.01"
                    min="0"
                    max="100"
                    required
                    hint-position="after"
                    :hint="' '"
                    wrapper-class="catalog-product-form__aligned-field catalog-product-form__aligned-field--spacer-hint"
                />
                <x-field.select :label="__('catalog.category')" :name="$categoryModel" :wire:model="$categoryModel" :required="($catalogUnitsSizesReady ?? false)">
                    <option value="">{{ __('catalog.none') }}</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->displayName() }}</option>
                    @endforeach
                </x-field.select>
                <div class="catalog-product-form__type-active-row" data-testid="catalog-product-type-active-row">
                    <x-field.select :label="__('catalog.item_type')" :name="$itemTypeModel" :wire:model="$itemTypeModel">
                        @foreach(\App\Enums\ProductItemType::cases() as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </x-field.select>
                    <div class="catalog-product-form__active-toggle">
                        <x-field.toggle :wire:model="$activeModel" :name="$activeModel" :label="__('catalog.active')" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div @class(['hidden' => $formTab !== 'details']) data-testid="catalog-product-form-details-panel">
        <div class="admin-modal-field-grid admin-modal-field-grid--2 catalog-product-form__tab-grid--dense">
            @if($variant === 'catalog')
                @if($catalogUnitsSizesReady ?? false)
                    <x-field.input
                        :label="__('catalog.sku')"
                        name="productSku"
                        wire:model="productSku"
                        readonly
                        disabled
                        hint-position="after"
                        :hint="' '"
                        wrapper-class="catalog-product-form__aligned-field catalog-product-form__aligned-field--spacer-hint admin-modal-span-2 form-field--span-2"
                    />
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
                    <x-field.select :label="__('catalog.vat_zatca_category')" name="productVatZatcaCategory" wire:model="productVatZatcaCategory" :required="($catalogUnitsSizesReady ?? false)" wrapper-class="admin-modal-span-2 form-field--span-2">
                        @foreach($vatZatcaCategoryOptions as $option)
                            <option value="{{ $option->value }}">{{ __('catalog.vat_zatca_'.$option->value) }}</option>
                        @endforeach
                    </x-field.select>
                @else
                    <x-field.input
                        :label="__('catalog.sku')"
                        name="productSku"
                        wire:model="productSku"
                        wrapper-class="admin-modal-span-2 form-field--span-2"
                    />
                @endif

                @if($catalogI18nReady ?? false)
                    <div class="catalog-product-form__descriptions admin-modal-span-2 form-field--span-2" data-testid="catalog-product-descriptions">
                        <x-field.textarea :label="__('catalog.description_en')" name="productDescriptionEn" wire:model="productDescriptionEn" :wrapper-class="$descriptionSpan" rows="3" />
                        <x-field.textarea :label="__('catalog.description_ar')" name="productDescriptionAr" wire:model="productDescriptionAr" dir="rtl" :wrapper-class="$descriptionSpan" rows="3" />
                        <x-field.textarea :label="__('catalog.description_bn')" name="productDescriptionBn" wire:model="productDescriptionBn" :wrapper-class="$descriptionSpan" rows="3" />
                    </div>
                @endif

                @if ($modifiersFeatureEnabled ?? false)
                    <fieldset class="catalog-product-form__modifiers admin-modal-span-2 form-field--span-2" data-testid="catalog-product-modifiers">
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

                @if($productStockReady ?? false)
                    <x-field.input
                        type="number"
                        step="0.01"
                        min="0"
                        :label="__('catalog.purchase_price')"
                        name="productCostInput"
                        wire:model="productCostInput"
                        inputmode="decimal"
                    />
                    <x-field.toggle wire:model.live="productTrackStock" name="productTrackStock" :label="__('stock.track_stock')" wrapper-class="admin-modal-span-2 form-field--span-2" />
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
                @else
                    <p class="admin-modal-span-2 form-field--span-2 text-sm text-[var(--app-muted)]">{{ __('catalog.product_stock_unavailable') }}</p>
                @endif
            @else
                @if($productStockReady ?? false)
                    <x-field.input
                        type="number"
                        step="0.01"
                        min="0"
                        :label="__('catalog.purchase_price')"
                        :name="$costModel"
                        :wire:model="$costModel"
                        :liveValidate="false"
                        inputmode="decimal"
                    />
                    <x-field.toggle :wire:model.live="$trackStockModel" :name="$trackStockModel" :label="__('stock.track_stock')" />
                @else
                    <p class="admin-modal-span-2 form-field--span-2 text-sm text-[var(--app-muted)]">{{ __('catalog.product_stock_unavailable') }}</p>
                @endif
            @endif
        </div>
    </div>
</div>