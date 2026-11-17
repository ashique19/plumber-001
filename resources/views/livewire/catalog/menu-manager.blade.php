<div class="catalog-admin-page">
<div class="catalog-admin min-w-0 max-w-full overflow-x-hidden pb-20 lg:pb-8">
    <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="app-page-title">{{ __('catalog.title') }}</h1>
            <p class="app-page-lead">{{ __('catalog.subtitle') }}</p>
        </div>
        <p class="text-xs text-[var(--app-muted)]">{{ __('catalog.price_hint') }}</p>
    </header>

    <div class="app-section-tabs app-section-tabs--scroll min-w-0 max-w-full gap-2">
        @php
            $catalogTabs = [
                'products' => __('catalog.tab_products'),
                'categories' => __('catalog.tab_categories'),
            ];
            if ($modifiersFeatureEnabled ?? false) {
                $catalogTabs['modifiers'] = __('catalog.tab_modifiers');
            }
            if ($catalogUnitsSizesReady ?? false) {
                $catalogTabs['units'] = __('catalog.tab_units');
                $catalogTabs['sizes'] = __('catalog.tab_sizes');
            }
        @endphp
        @foreach ($catalogTabs as $key => $label)
            <button
                type="button"
                wire:click="setTab('{{ $key }}')"
                @class(['app-chip', 'app-chip--active' => $tab === $key])
                @if ($key === 'modifiers') data-testid="catalog-tab-modifiers" @endif
            >{{ $label }}</button>
        @endforeach
        <label class="form-field ms-auto w-full min-w-0 sm:w-auto sm:max-w-xs">
            <span class="form-field__label">{{ __('catalog.price_mode') }}</span>
            <select wire:model.live="priceMode" class="form-field__control w-full sm:min-w-[12rem]">
                <option value="ex">{{ __('catalog.price_ex_vat') }}</option>
                <option value="inc">{{ __('catalog.price_inc_vat') }}</option>
            </select>
        </label>
    </div>

    @if ($tab === 'categories')
        <section class="mt-4">
            @if($canWrite)
                <x-app.add-button type="button" variant="primary" data-admin-livewire-call="startCategory">{{ __('catalog.add_category') }}</x-app.add-button>
            @endif
            <ul class="space-y-3">
                @forelse($categories as $cat)
                    <li class="app-card flex flex-wrap items-center justify-between gap-2 p-3">
                        <div class="flex items-center gap-2">
                            <x-app.icon :name="$cat->icon_key ?: 'cup'" class="h-5 w-5 text-[var(--app-accent)]" />
                            <div>
                                <p class="font-medium">{{ $cat->displayName() }}</p>
                                @if($cat->name_ar && $cat->displayName() !== $cat->name_ar)
                                    <p class="text-xs text-[var(--app-muted)]" dir="rtl">{{ $cat->name_ar }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span @class(['pos-status-pill', 'pos-status-pill--paid' => $cat->is_active, 'pos-status-pill--cancelled' => ! $cat->is_active])>
                                {{ $cat->is_active ? __('catalog.active') : __('catalog.inactive') }}
                            </span>
                            @if($canWrite)
                                <button type="button" class="tap-action-muted text-xs" wire:click="startCategory({{ $cat->id }})">{{ __('catalog.edit') }}</button>
                                <x-ui.livewire-action wire:click="deleteCategory({{ $cat->id }})" :target="'deleteCategory('.$cat->id.')'" loading="saving" variant="muted" class="tap-action-muted text-xs text-rose-600">{{ __('catalog.delete') }}</x-ui.livewire-action>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="app-empty"><p class="text-sm text-[var(--app-muted)]">{{ __('catalog.empty_categories') }}</p></li>
                @endforelse
            </ul>
        </section>
    @endif

    @if ($tab === 'products')
        <section>
            @include('livewire.catalog.partials.product-gallery-toolbar', ['categories' => $categories])

            @if ($productsView === 'list')
                @include('livewire.catalog.partials.product-list-legacy')
            @else
                @include('livewire.catalog.partials.product-gallery-grid')
            @endif
        </section>
    @endif

    @if (($modifiersFeatureEnabled ?? false) && $tab === 'modifiers')
        <section class="mt-4">
            @if($canWrite)
                <x-app.add-button type="button" variant="primary" data-admin-livewire-call="startModifier">{{ __('catalog.add_modifier') }}</x-app.add-button>
            @endif
            <ul class="space-y-3">
                @forelse($modifiers as $mod)
                    <li class="app-card flex items-center justify-between gap-2 p-3">
                        <div>
                            <p class="font-medium">{{ $mod->displayName() }}</p>
                            <p class="text-sm tabular-nums text-[var(--app-muted)]">
                                + {{ number_format(\App\Support\VatPricing::incFromEx((float) $mod->price_ex_vat, (float) $mod->vat_rate), 2) }} {{ __('dashboard.sar') }}
                            </p>
                        </div>
                        @if($canWrite)
                            <div class="flex gap-2">
                                <button type="button" class="tap-action-muted text-xs" wire:click="startModifier({{ $mod->id }})">{{ __('catalog.edit') }}</button>
                                <x-ui.livewire-action wire:click="deleteModifier({{ $mod->id }})" :target="'deleteModifier('.$mod->id.')'" loading="saving" variant="muted" class="tap-action-muted text-xs text-rose-600">{{ __('catalog.delete') }}</x-ui.livewire-action>
                            </div>
                        @endif
                    </li>
                @empty
                    <li class="app-empty"><p class="text-sm text-[var(--app-muted)]">{{ __('catalog.empty_modifiers') }}</p></li>
                @endforelse
            </ul>
        </section>
    @endif

    @if (($catalogUnitsSizesReady ?? false) && $tab === 'units')
        <section class="mt-4">
            @if($canWrite)
                <x-app.add-button type="button" wire:click="startUnit">{{ __('catalog.add_unit') }}</x-app.add-button>
            @endif
            <ul class="mt-3 space-y-3">
                @forelse($catalogUnits as $unit)
                    <li class="app-card flex flex-wrap items-center justify-between gap-2 p-3">
                        <div>
                            <p class="font-medium">{{ $unit->displayName() }} <span class="text-xs text-[var(--app-muted)]">({{ $unit->code }})</span></p>
                            <p class="text-xs text-[var(--app-muted)]">UBL: {{ $unit->ubl_unit_code }}</p>
                        </div>
                        @if($canWrite)
                            <button type="button" class="tap-action-muted text-xs" wire:click="startUnit({{ $unit->id }})">{{ __('catalog.edit') }}</button>
                        @endif
                    </li>
                @empty
                    <li class="app-empty"><p class="text-sm text-[var(--app-muted)]">{{ __('catalog.empty_units') }}</p></li>
                @endforelse
            </ul>
        </section>
    @endif

    @if (($catalogUnitsSizesReady ?? false) && $tab === 'sizes')
        <section class="mt-4">
            @if($canWrite)
                <x-app.add-button type="button" wire:click="startSize">{{ __('catalog.add_size') }}</x-app.add-button>
            @endif
            <ul class="mt-3 space-y-3">
                @forelse($catalogSizes as $size)
                    <li class="app-card flex flex-wrap items-center justify-between gap-2 p-3">
                        <div>
                            <p class="font-medium">{{ $size->name }}</p>
                            @if($size->measurement_type)
                                <p class="text-xs text-[var(--app-muted)]">{{ $size->measurement_type }}</p>
                            @endif
                        </div>
                        @if($canWrite)
                            <button type="button" class="tap-action-muted text-xs" wire:click="startSize({{ $size->id }})">{{ __('catalog.edit') }}</button>
                        @endif
                    </li>
                @empty
                    <li class="app-empty"><p class="text-sm text-[var(--app-muted)]">{{ __('catalog.empty_sizes') }}</p></li>
                @endforelse
            </ul>
        </section>
    @endif

</div>

    @if($showCategoryForm && $canWrite)
        <x-modal
            :show="true"
            :title="$editingCategoryId ? __('catalog.edit_category') : __('catalog.add_category')"
            close-wire='$set("showCategoryForm", false)'
            size="xl"
            class="x-modal--form-sheet"
            data-testid="catalog-category-modal"
        >
            <form id="catalog-category-form" class="x-modal-form" wire:submit="saveCategory">
                <div class="x-modal-form__fields app-admin-form">
                    @include('livewire.catalog.partials.category-form-fields')
                </div>
                <div class="x-modal__footer x-modal__footer--in-form">
                    <x-modal.footer
                        cancel-wire='$set("showCategoryForm", false)'
                        submit-target="saveCategory"
                        :submit-label="__('catalog.save')"
                    />
                </div>
            </form>
        </x-modal>
    @endif

    @if($showProductForm && $canWrite)
        <x-modal
            :show="true"
            :title="$editingProductId ? __('catalog.edit_product') : __('catalog.add_product')"
            close-wire='$set("showProductForm", false)'
            size="xl"
            data-testid="catalog-product-modal"
        >
            <form id="catalog-product-form" class="x-modal-form catalog-product-form" wire:submit="saveProduct">
                <x-modal.validation-summary :fields="[
                    'productNameEn', 'productNameAr', 'productNameBn', 'productCategoryId', 'productUnitId', 'productSizeId',
                    'productCostInput', 'productPriceInput', 'productVatRate', 'productVatZatcaCategory', 'productOpeningStock', 'productImageUpload',
                ]" />
                <div class="catalog-product-form__body min-h-0 flex flex-1 flex-col overflow-hidden">
                    @include('livewire.catalog.partials.product-form-fields')
                </div>
                <div class="x-modal__footer x-modal__footer--in-form">
                    <x-modal.footer
                        cancel-wire='$set("showProductForm", false)'
                        submit-target="saveProduct"
                        :submit-label="__('catalog.save')"
                    />
                </div>
            </form>
        </x-modal>
    @endif

    @if($showIngredientsModal && $canWrite)
        <x-modal
            :show="true"
            :title="__('inventory.ingredients_modal_title')"
            close-wire='$set("showIngredientsModal", false)'
            size="lg"
            data-testid="catalog-ingredients-modal"
        >
            <div class="x-modal-form">
            <div class="x-modal-form__fields app-admin-form">
                @foreach($ingredientLines as $index => $line)
                    <div class="grid grid-cols-1 gap-2 border-b pb-3 sm:grid-cols-1" style="border-color: var(--app-border)" wire:key="ing-line-{{ $index }}">
                        <x-field.select :label="__('inventory.nav')" name="ingredientLines.{{ $index }}.ingredient_id" wire:model="ingredientLines.{{ $index }}.ingredient_id">
                            <option value="">{{ __('catalog.none') }}</option>
                            @foreach($ingredientOptions as $opt)
                                <option value="{{ $opt->id }}">{{ $opt->displayName() }} ({{ $opt->unit }})</option>
                            @endforeach
                        </x-field.select>
                        <div class="flex flex-wrap items-end gap-2">
                            <div class="min-w-0 flex-1">
                                <x-field.input type="number" :label="__('inventory.quantity_per_unit')" name="ingredientLines.{{ $index }}.quantity_per_unit" wire:model="ingredientLines.{{ $index }}.quantity_per_unit" inputmode="decimal" />
                            </div>
                            <button type="button" class="tap-action-muted min-h-11 shrink-0 px-3 text-sm text-rose-600" wire:click="removeIngredientLine({{ $index }})">{{ __('catalog.delete') }}</button>
                        </div>
                    </div>
                @endforeach
                <button type="button" class="tap-action-muted min-h-11 w-full text-sm font-semibold" wire:click="addIngredientLine">{{ __('inventory.add') }}</button>
                <form class="rounded-xl border p-3" style="border-color: var(--app-border)" wire:submit="quickCreateIngredient">
                    <p class="text-xs font-medium text-[var(--app-muted)]">{{ __('inventory.quick_create') }}</p>
                    <div class="mt-2 grid grid-cols-1 gap-2">
                        <x-field.input :label="__('catalog.name_en')" name="quickIngredientNameEn" wire:model="quickIngredientNameEn" />
                        <x-field.input :label="__('inventory.unit')" name="quickIngredientUnit" wire:model="quickIngredientUnit" />
                    </div>
                    <x-ui.livewire-action type="submit" target="quickCreateIngredient" loading="saving" class="tap-action-muted mt-2 min-h-11 w-full text-sm" variant="muted">{{ __('inventory.quick_create') }}</x-ui.livewire-action>
                </form>
            </div>
            </div>
            <x-slot name="footer">
                <x-modal.footer
                    cancel-wire='$set("showIngredientsModal", false)'
                    submit-wire="saveProductIngredients"
                    submit-target="saveProductIngredients"
                    :submit-label="__('catalog.save')"
                />
            </x-slot>
        </x-modal>
    @endif

    @if(($modifiersFeatureEnabled ?? false) && $showModifierForm && $canWrite)
        <x-modal
            :show="true"
            :title="$editingModifierId ? __('catalog.edit_modifier') : __('catalog.add_modifier')"
            close-wire='$set("showModifierForm", false)'
            size="xl"
            class="x-modal--form-sheet"
            data-testid="catalog-modifier-modal"
        >
            <form id="catalog-modifier-form" class="x-modal-form" wire:submit="saveModifier">
                <div class="x-modal-form__fields app-admin-form">
                    @include('livewire.catalog.partials.modifier-form-fields')
                </div>
                <div class="x-modal__footer x-modal__footer--in-form">
                    <x-modal.footer
                        cancel-wire='$set("showModifierForm", false)'
                        submit-target="saveModifier"
                        :submit-label="__('catalog.save')"
                    />
                </div>
            </form>
        </x-modal>
    @endif

    @if(($catalogUnitsSizesReady ?? false) && $showUnitForm && $canWrite)
        <x-modal
            :show="true"
            :title="$editingUnitId ? __('catalog.edit_unit') : __('catalog.add_unit')"
            close-wire='$set("showUnitForm", false)'
        >
            <form id="catalog-unit-form" class="x-modal-form" wire:submit="saveUnit">
                <div class="x-modal-form__fields app-admin-form">
                <x-field.input :label="__('catalog.name_en')" name="unitNameEn" wire:model="unitNameEn" required />
                <x-field.input :label="__('catalog.name_ar')" name="unitNameAr" wire:model="unitNameAr" dir="rtl" required />
                <x-field.input :label="__('catalog.name_bn')" name="unitNameBn" wire:model="unitNameBn" required />
                <x-field.input :label="__('catalog.unit_code')" name="unitCode" wire:model="unitCode" required />
                <x-field.toggle wire:model="unitActive" name="unitActive" :label="__('catalog.active')" />
                </div>
                <div class="x-modal__footer x-modal__footer--in-form">
                    <x-modal.footer
                        cancel-wire='$set("showUnitForm", false)'
                        submit-target="saveUnit"
                        :submit-label="__('catalog.save')"
                    />
                </div>
            </form>
        </x-modal>
    @endif

    @if(($catalogUnitsSizesReady ?? false) && $showSizeForm && $canWrite)
        <x-modal
            :show="true"
            :title="$editingSizeId ? __('catalog.edit_size') : __('catalog.add_size')"
            close-wire='$set("showSizeForm", false)'
        >
            <form id="catalog-size-form" class="x-modal-form" wire:submit="saveSize">
                <div class="x-modal-form__fields app-admin-form">
                <x-field.input :label="__('catalog.name_en')" name="sizeName" wire:model="sizeName" required />
                <x-field.input :label="__('catalog.measurement_type')" name="sizeMeasurementType" wire:model="sizeMeasurementType" />
                <x-field.toggle wire:model="sizeActive" name="sizeActive" :label="__('catalog.active')" />
                </div>
                <div class="x-modal__footer x-modal__footer--in-form">
                    <x-modal.footer
                        cancel-wire='$set("showSizeForm", false)'
                        submit-target="saveSize"
                        :submit-label="__('catalog.save')"
                    />
                </div>
            </form>
        </x-modal>
    @endif

    @include('livewire.catalog.partials.product-action-modal')
</div>
