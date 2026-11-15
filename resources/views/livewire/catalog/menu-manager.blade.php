<div class="catalog-admin min-w-0 max-w-full overflow-x-hidden pb-20 lg:pb-8">
    <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="app-page-title">{{ __('catalog.title') }}</h1>
            <p class="app-page-lead">{{ __('catalog.subtitle') }}</p>
        </div>
        <p class="text-xs text-[var(--app-muted)]">{{ __('catalog.price_hint') }}</p>
    </header>

    <div class="app-section-tabs app-section-tabs--scroll min-w-0 max-w-full gap-2">
        @foreach (['products' => __('catalog.tab_products'), 'categories' => __('catalog.tab_categories'), 'modifiers' => __('catalog.tab_modifiers')] as $key => $label)
            <button
                type="button"
                wire:click="setTab('{{ $key }}')"
                @class(['app-chip', 'app-chip--active' => $tab === $key])
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
                <x-app.add-button type="button" wire:click="startCategory()">{{ __('catalog.add_category') }}</x-app.add-button>
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
        <section class="mt-4">
            @if($canWrite)
                <x-app.add-button type="button" variant="primary" wire:click="startProduct()">{{ __('catalog.add_product') }}</x-app.add-button>
            @endif
            <ul class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                @forelse($products as $product)
                    <li class="app-card overflow-hidden p-0">
                        <div class="flex gap-3 p-3">
                            @if($product->image_url)
                                <img src="{{ asset($product->image_url) }}" alt="" class="h-16 w-16 rounded-lg object-cover" loading="lazy">
                            @else
                                <div class="flex h-16 w-16 items-center justify-center rounded-lg bg-[var(--plumber-sky)] text-xs text-[var(--app-muted)]">—</div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold">{{ $product->displayName() }}</p>
                                <p class="text-xs text-[var(--app-muted)]">{{ $product->category?->displayName() }}</p>
                                <p class="mt-1 text-sm font-medium tabular-nums">
                                    {{ number_format(\App\Support\VatPricing::incFromEx((float) $product->price_ex_vat, (float) $product->vat_rate), 2) }} {{ __('dashboard.sar') }}
                                </p>
                                @if($storefrontVisibilityReady ?? false)
                                    <p class="mt-2 flex flex-wrap gap-1.5 text-[0.65rem] font-semibold uppercase tracking-wide text-[var(--app-muted)]">
                                        @if($product->show_on_homepage)
                                            <span class="pos-status-pill pos-status-pill--paid">{{ __('catalog.show_on_homepage_short') }}</span>
                                        @endif
                                        @if($product->show_on_menu)
                                            <span class="pos-status-pill pos-status-pill--paid">{{ __('catalog.show_on_menu_short') }}</span>
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </div>
                        @if($canWrite)
                            <div class="flex flex-wrap items-center gap-2 border-t px-3 py-2 text-xs" style="border-color: var(--app-border)">
                                @if($storefrontVisibilityReady ?? false)
                                    <button type="button" class="tap-action-muted min-h-9 px-2" wire:click="toggleProductShowOnHomepage({{ $product->id }})" wire:loading.attr="disabled" wire:target="toggleProductShowOnHomepage({{ $product->id }})">
                                        {{ $product->show_on_homepage ? __('catalog.hide_on_homepage') : __('catalog.show_on_homepage') }}
                                    </button>
                                    <button type="button" class="tap-action-muted min-h-9 px-2" wire:click="toggleProductShowOnMenu({{ $product->id }})" wire:loading.attr="disabled" wire:target="toggleProductShowOnMenu({{ $product->id }})">
                                        {{ $product->show_on_menu ? __('catalog.hide_on_menu') : __('catalog.show_on_menu') }}
                                    </button>
                                @endif
                                @if($inventoryReady ?? false)
                                    <button type="button" class="tap-action-muted" wire:click="openProductIngredients({{ $product->id }})">{{ __('inventory.ingredients_button') }}</button>
                                @endif
                                <button type="button" class="tap-action-muted ms-auto" wire:click="startProduct({{ $product->id }})">{{ __('catalog.edit') }}</button>
                                <x-ui.livewire-action wire:click="deleteProduct({{ $product->id }})" :target="'deleteProduct('.$product->id.')'" loading="saving" variant="muted" class="tap-action-muted ms-auto text-rose-600">{{ __('catalog.delete') }}</x-ui.livewire-action>
                            </div>
                        @endif
                    </li>
                @empty
                    <li class="app-empty lg:col-span-2"><p class="text-sm text-[var(--app-muted)]">{{ __('catalog.empty_products') }}</p></li>
                @endforelse
            </ul>
        </section>
    @endif

    @if ($tab === 'modifiers')
        <section class="mt-4">
            @if($canWrite)
                <x-app.add-button type="button" wire:click="startModifier()">{{ __('catalog.add_modifier') }}</x-app.add-button>
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

    @if($showCategoryForm && $canWrite)
        <div class="app-sheet-backdrop" wire:click="$set('showCategoryForm', false)"></div>
        <x-ui.admin-sheet
            :title="$editingCategoryId ? __('catalog.edit_category') : __('catalog.add_category')"
            close-wire='$set("showCategoryForm", false)'
            form-id="catalog-category-form"
            submit-target="saveCategory"
        >
            <form id="catalog-category-form" class="app-admin-form" wire:submit="saveCategory">
                <x-field.input :label="__('catalog.name_en')" name="categoryNameEn" wire:model="categoryNameEn" required />
                <x-field.input :label="__('catalog.name_ar')" name="categoryNameAr" wire:model="categoryNameAr" dir="rtl" />
                @if($categoryBanglaFieldsReady ?? false)
                    <x-field.input :label="__('catalog.name_bn')" name="categoryNameBn" wire:model="categoryNameBn" />
                @endif
                @if($categoryDescriptionFieldsReady ?? false)
                    <x-field.textarea :label="__('catalog.description_en')" name="categoryDescriptionEn" wire:model="categoryDescriptionEn" />
                    <x-field.textarea :label="__('catalog.description_ar')" name="categoryDescriptionAr" wire:model="categoryDescriptionAr" dir="rtl" />
                    <x-field.textarea :label="__('catalog.description_bn')" name="categoryDescriptionBn" wire:model="categoryDescriptionBn" />
                @endif
                <x-field.input :label="__('catalog.icon_key')" name="categoryIconKey" wire:model="categoryIconKey" />
                <x-field.toggle wire:model="categoryActive" name="categoryActive" :label="__('catalog.active')" />
            </form>
        </x-ui.admin-sheet>
    @endif

    @if($showProductForm && $canWrite)
        <div class="app-sheet-backdrop" wire:click="$set('showProductForm', false)"></div>
        <x-ui.admin-sheet
            :title="$editingProductId ? __('catalog.edit_product') : __('catalog.add_product')"
            close-wire='$set("showProductForm", false)'
            form-id="catalog-product-form"
            submit-target="saveProduct"
            wide
        >
            <form id="catalog-product-form" class="app-admin-form" wire:submit="saveProduct">
                <x-field.input :label="__('catalog.name_en')" name="productNameEn" wire:model="productNameEn" required />
                <x-field.input :label="__('catalog.name_ar')" name="productNameAr" wire:model="productNameAr" dir="rtl" required />
                @if($catalogI18nReady ?? false)
                    <x-field.input :label="__('catalog.name_bn')" name="productNameBn" wire:model="productNameBn" />
                    <x-field.textarea :label="__('catalog.description_en')" name="productDescriptionEn" wire:model="productDescriptionEn" />
                    <x-field.textarea :label="__('catalog.description_ar')" name="productDescriptionAr" wire:model="productDescriptionAr" dir="rtl" />
                    <x-field.textarea :label="__('catalog.description_bn')" name="productDescriptionBn" wire:model="productDescriptionBn" />
                @endif
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <x-field.input :label="__('catalog.sku')" name="productSku" wire:model="productSku" />
                    <x-field.select :label="__('catalog.category')" name="productCategoryId" wire:model="productCategoryId">
                        <option value="">{{ __('catalog.none') }}</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->displayName() }}</option>
                        @endforeach
                    </x-field.select>
                </div>
                <x-form.product-image-upload
                    :label="__('catalog.product_image.label')"
                    wire-model="productImageUpload"
                    :preview-url="$productImageRemove ? null : $productExistingImage"
                />
                <div class="app-admin-form__row--price grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <x-field.input type="number" :label="__('catalog.price').' ('.($priceMode === 'inc' ? __('catalog.inc_short') : __('catalog.ex_short')).')'" name="productPriceInput" wire:model="productPriceInput" required inputmode="decimal" />
                    <x-field.input type="number" :label="__('catalog.vat_rate')" name="productVatRate" wire:model="productVatRate" inputmode="decimal" />
                </div>
                <fieldset>
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
                <x-field.toggle wire:model="productActive" name="productActive" :label="__('catalog.active')" />
                @if($productStockReady ?? false)
                    <x-field.toggle wire:model="productTrackStock" name="productTrackStock" :label="__('stock.track_stock')" />
                    <x-field.input type="number" :label="__('stock.low_stock_threshold')" name="productLowStockThreshold" wire:model="productLowStockThreshold" inputmode="decimal" />
                @endif
            </form>
        </x-ui.admin-sheet>
    @endif

    @if($showIngredientsModal && $canWrite)
        <div class="app-sheet-backdrop" wire:click="$set('showIngredientsModal', false)"></div>
        <x-ui.admin-sheet
            :title="__('inventory.ingredients_modal_title')"
            close-wire='$set("showIngredientsModal", false)'
            wide
        >
            <x-slot name="footer">
                <button type="button" class="tap-action-muted min-h-11 flex-1 text-sm font-semibold" wire:click="$set('showIngredientsModal', false)">{{ __('catalog.cancel') }}</button>
                <x-ui.livewire-action wire:click="saveProductIngredients" target="saveProductIngredients" loading="saving" class="tap-action-primary min-h-11 flex-1 text-sm font-semibold" variant="primary">{{ __('catalog.save') }}</x-ui.livewire-action>
            </x-slot>
            <div class="app-admin-form">
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
        </x-ui.admin-sheet>
    @endif

    @if($showModifierForm && $canWrite)
        <div class="app-sheet-backdrop" wire:click="$set('showModifierForm', false)"></div>
        <x-ui.admin-sheet
            :title="$editingModifierId ? __('catalog.edit_modifier') : __('catalog.add_modifier')"
            close-wire='$set("showModifierForm", false)'
            form-id="catalog-modifier-form"
            submit-target="saveModifier"
        >
            <form id="catalog-modifier-form" class="app-admin-form" wire:submit="saveModifier">
                <x-field.input :label="__('catalog.name_en')" name="modifierNameEn" wire:model="modifierNameEn" required />
                <x-field.input :label="__('catalog.name_ar')" name="modifierNameAr" wire:model="modifierNameAr" dir="rtl" />
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <x-field.input type="number" :label="__('catalog.price')" name="modifierPriceInput" wire:model="modifierPriceInput" required inputmode="decimal" />
                    <x-field.input type="number" :label="__('catalog.vat_rate')" name="modifierVatRate" wire:model="modifierVatRate" inputmode="decimal" />
                </div>
                <x-field.toggle wire:model="modifierActive" name="modifierActive" :label="__('catalog.active')" />
            </form>
        </x-ui.admin-sheet>
    @endif
</div>
