<div class="catalog-admin pb-24 lg:pb-8">
    <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="app-page-title">{{ __('catalog.title') }}</h1>
            <p class="app-page-lead">{{ __('catalog.subtitle') }}</p>
        </div>
        <p class="text-xs text-[var(--app-muted)]">{{ __('catalog.price_hint') }}</p>
    </header>

    <div class="app-section-tabs">
        @foreach (['products' => __('catalog.tab_products'), 'categories' => __('catalog.tab_categories'), 'modifiers' => __('catalog.tab_modifiers')] as $key => $label)
            <button
                type="button"
                wire:click="setTab('{{ $key }}')"
                @class(['app-chip', 'app-chip--active' => $tab === $key])
            >{{ $label }}</button>
        @endforeach
        <label class="ms-auto flex w-full flex-wrap items-center gap-2 text-xs font-medium text-[var(--app-muted)] sm:w-auto">
            <span>{{ __('catalog.price_mode') }}</span>
            <select wire:model.live="priceMode" class="app-toolbar-select min-h-11 flex-1 sm:flex-none">
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
                                <p class="font-medium">{{ $cat->name_en }}</p>
                                @if($cat->name_ar)
                                    <p class="text-xs text-[var(--app-muted)]">{{ $cat->name_ar }}</p>
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
                <x-app.add-button type="button" wire:click="startProduct()">{{ __('catalog.add_product') }}</x-app.add-button>
            @endif
            <ul class="grid gap-3 sm:grid-cols-2">
                @forelse($products as $product)
                    <li class="app-card overflow-hidden p-0">
                        <div class="flex gap-3 p-3">
                            @if($product->image_url)
                                <img src="{{ asset($product->image_url) }}" alt="" class="h-16 w-16 rounded-lg object-cover" loading="lazy">
                            @else
                                <div class="flex h-16 w-16 items-center justify-center rounded-lg bg-[var(--cafe-latte)] text-xs text-[var(--app-muted)]">—</div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold">{{ $product->name_en }}</p>
                                <p class="text-xs text-[var(--app-muted)]">{{ $product->category?->name_en }}</p>
                                <p class="mt-1 text-sm font-medium tabular-nums">
                                    {{ number_format(\App\Support\VatPricing::incFromEx((float) $product->price_ex_vat, (float) $product->vat_rate), 2) }} SAR
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
                    <li class="app-empty sm:col-span-2"><p class="text-sm text-[var(--app-muted)]">{{ __('catalog.empty_products') }}</p></li>
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
                            <p class="font-medium">{{ $mod->name_en }}</p>
                            <p class="text-sm tabular-nums text-[var(--app-muted)]">
                                + {{ number_format(\App\Support\VatPricing::incFromEx((float) $mod->price_ex_vat, (float) $mod->vat_rate), 2) }} SAR
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
        <div class="app-sheet-backdrop fixed inset-0 z-40 bg-black/40" wire:click="$set('showCategoryForm', false)"></div>
        <div class="app-sheet fixed inset-x-0 bottom-0 z-50 max-h-[85vh] overflow-y-auto p-4 sm:inset-auto sm:start-1/2 sm:top-1/2 sm:w-full sm:max-w-md sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-2xl" role="dialog">
            <h2 class="text-lg font-semibold">{{ $editingCategoryId ? __('catalog.edit_category') : __('catalog.add_category') }}</h2>
            <form class="mt-4 space-y-3" wire:submit="saveCategory">
                <x-form.input :label="__('catalog.name_en')" name="categoryNameEn" wire:model="categoryNameEn" required />
                <x-form.input :label="__('catalog.name_ar')" name="categoryNameAr" wire:model="categoryNameAr" dir="rtl" />
                @if($catalogI18nReady ?? false)
                    <x-form.input :label="__('catalog.name_bn')" name="categoryNameBn" wire:model="categoryNameBn" />
                    <x-form.textarea :label="__('catalog.description_en')" name="categoryDescriptionEn" wire:model="categoryDescriptionEn" wrapperClass="sm:col-span-2" />
                    <x-form.textarea :label="__('catalog.description_ar')" name="categoryDescriptionAr" wire:model="categoryDescriptionAr" dir="rtl" wrapperClass="sm:col-span-2" />
                    <x-form.textarea :label="__('catalog.description_bn')" name="categoryDescriptionBn" wire:model="categoryDescriptionBn" wrapperClass="sm:col-span-2" />
                @endif
                <x-form.input :label="__('catalog.icon_key')" name="categoryIconKey" wire:model="categoryIconKey" />
                <x-form.toggle wire:model="categoryActive" name="categoryActive" :label="__('catalog.active')" />
                <x-ui.livewire-action type="submit" target="saveCategory" loading="saving" class="tap-action-primary w-full" variant="primary">{{ __('catalog.save') }}</x-ui.livewire-action>
            </form>
        </div>
    @endif

    @if($showProductForm && $canWrite)
        <div class="app-sheet-backdrop fixed inset-0 z-40 bg-black/40" wire:click="$set('showProductForm', false)"></div>
        <div class="app-sheet fixed inset-x-0 bottom-0 z-50 max-h-[90vh] overflow-y-auto p-4 sm:inset-auto sm:start-1/2 sm:top-1/2 sm:w-full sm:max-w-lg sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-2xl">
            <h2 class="text-lg font-semibold">{{ $editingProductId ? __('catalog.edit_product') : __('catalog.add_product') }}</h2>
            <form class="mt-4 grid gap-3 sm:grid-cols-2" wire:submit="saveProduct">
                <x-form.input :label="__('catalog.name_en')" name="productNameEn" wire:model="productNameEn" required wrapperClass="sm:col-span-2" />
                <x-form.input :label="__('catalog.name_ar')" name="productNameAr" wire:model="productNameAr" dir="rtl" required wrapperClass="sm:col-span-2" />
                @if($catalogI18nReady ?? false)
                    <x-form.input :label="__('catalog.name_bn')" name="productNameBn" wire:model="productNameBn" wrapperClass="sm:col-span-2" />
                    <x-form.textarea :label="__('catalog.description_en')" name="productDescriptionEn" wire:model="productDescriptionEn" wrapperClass="sm:col-span-2" />
                    <x-form.textarea :label="__('catalog.description_ar')" name="productDescriptionAr" wire:model="productDescriptionAr" dir="rtl" wrapperClass="sm:col-span-2" />
                    <x-form.textarea :label="__('catalog.description_bn')" name="productDescriptionBn" wire:model="productDescriptionBn" wrapperClass="sm:col-span-2" />
                @endif
                <x-form.input :label="__('catalog.sku')" name="productSku" wire:model="productSku" />
                <x-form.select :label="__('catalog.category')" name="productCategoryId" wire:model="productCategoryId">
                    <option value="">—</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name_en }}</option>
                    @endforeach
                </x-form.select>
                <x-form.input :label="__('catalog.image_url')" name="productImageUrl" wire:model="productImageUrl" placeholder="images/fancy-coffee/latte.webp" wrapperClass="sm:col-span-2" />
                <x-form.input type="number" :label="__('catalog.price').' ('.($priceMode === 'inc' ? __('catalog.inc_short') : __('catalog.ex_short')).')'" name="productPriceInput" wire:model="productPriceInput" required />
                <x-form.input type="number" :label="__('catalog.vat_rate')" name="productVatRate" wire:model="productVatRate" />
                <fieldset class="sm:col-span-2">
                    <legend class="text-xs font-medium text-[var(--app-muted)]">{{ __('catalog.modifiers') }}</legend>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($modifiers as $mod)
                            <label class="flex items-center gap-1 rounded-full px-2 py-1 text-xs ring-1" style="border-color: var(--app-border)">
                                <input type="checkbox" value="{{ $mod->id }}" wire:model="productModifierIds"> {{ $mod->name_en }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <x-form.toggle wire:model="productActive" name="productActive" :label="__('catalog.active')" class="sm:col-span-2" />
                @if($storefrontVisibilityReady ?? false)
                    <x-form.toggle wire:model="productShowOnHomepage" name="productShowOnHomepage" :label="__('catalog.show_on_homepage')" class="sm:col-span-1" />
                    <x-form.toggle wire:model="productShowOnMenu" name="productShowOnMenu" :label="__('catalog.show_on_menu')" class="sm:col-span-1" />
                @endif
                <x-ui.livewire-action type="submit" target="saveProduct" loading="saving" class="tap-action-primary w-full sm:col-span-2" variant="primary">{{ __('catalog.save') }}</x-ui.livewire-action>
            </form>
        </div>
    @endif

    @if($showIngredientsModal && $canWrite)
        <div class="app-sheet-backdrop fixed inset-0 z-40 bg-black/40" wire:click="$set('showIngredientsModal', false)"></div>
        <div class="app-sheet fixed inset-x-0 bottom-0 z-50 max-h-[90vh] overflow-y-auto p-4 sm:inset-auto sm:start-1/2 sm:top-1/2 sm:w-full sm:max-w-lg sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-2xl">
            <h2 class="text-lg font-semibold">{{ __('inventory.ingredients_modal_title') }}</h2>
            <div class="mt-3 space-y-3">
                @foreach($ingredientLines as $index => $line)
                    <div class="flex flex-wrap items-end gap-2" wire:key="ing-line-{{ $index }}">
                        <div class="min-w-0 flex-1">
                            <x-form.select :label="__('inventory.nav')" name="ingredientLines.{{ $index }}.ingredient_id" wire:model="ingredientLines.{{ $index }}.ingredient_id">
                                <option value="">—</option>
                                @foreach($ingredientOptions as $opt)
                                    <option value="{{ $opt->id }}">{{ $opt->displayName() }} ({{ $opt->unit }})</option>
                                @endforeach
                            </x-form.select>
                        </div>
                        <x-form.input type="number" :label="__('inventory.quantity_per_unit')" name="ingredientLines.{{ $index }}.quantity_per_unit" wire:model="ingredientLines.{{ $index }}.quantity_per_unit" />
                        <button type="button" class="tap-action-muted text-xs text-rose-600" wire:click="removeIngredientLine({{ $index }})">{{ __('catalog.delete') }}</button>
                    </div>
                @endforeach
                <button type="button" class="tap-action-muted text-sm" wire:click="addIngredientLine">{{ __('inventory.add') }}</button>
            </div>
            <form class="mt-4 rounded-xl border p-3" style="border-color: var(--app-border)" wire:submit="quickCreateIngredient">
                <p class="text-xs font-medium text-[var(--app-muted)]">{{ __('inventory.quick_create') }}</p>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <x-form.input :label="__('catalog.name_en')" name="quickIngredientNameEn" wire:model="quickIngredientNameEn" />
                    <x-form.input :label="__('inventory.unit')" name="quickIngredientUnit" wire:model="quickIngredientUnit" />
                </div>
                <x-ui.livewire-action type="submit" target="quickCreateIngredient" loading="saving" class="tap-action-muted mt-2 text-sm" variant="muted">{{ __('inventory.quick_create') }}</x-ui.livewire-action>
            </form>
            <x-ui.livewire-action wire:click="saveProductIngredients" target="saveProductIngredients" loading="saving" class="tap-action-primary mt-4 w-full" variant="primary">{{ __('catalog.save') }}</x-ui.livewire-action>
        </div>
    @endif

    @if($showModifierForm && $canWrite)
        <div class="app-sheet-backdrop fixed inset-0 z-40 bg-black/40" wire:click="$set('showModifierForm', false)"></div>
        <div class="app-sheet fixed inset-x-0 bottom-0 z-50 max-h-[85vh] overflow-y-auto p-4 sm:inset-auto sm:start-1/2 sm:top-1/2 sm:w-full sm:max-w-md sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-2xl">
            <h2 class="text-lg font-semibold">{{ $editingModifierId ? __('catalog.edit_modifier') : __('catalog.add_modifier') }}</h2>
            <form class="mt-4 space-y-3" wire:submit="saveModifier">
                <x-form.input :label="__('catalog.name_en')" name="modifierNameEn" wire:model="modifierNameEn" required />
                <x-form.input :label="__('catalog.name_ar')" name="modifierNameAr" wire:model="modifierNameAr" dir="rtl" />
                <x-form.input type="number" :label="__('catalog.price')" name="modifierPriceInput" wire:model="modifierPriceInput" required />
                <x-form.input type="number" :label="__('catalog.vat_rate')" name="modifierVatRate" wire:model="modifierVatRate" />
                <x-form.toggle wire:model="modifierActive" name="modifierActive" :label="__('catalog.active')" />
                <x-ui.livewire-action type="submit" target="saveModifier" loading="saving" class="tap-action-primary w-full" variant="primary">{{ __('catalog.save') }}</x-ui.livewire-action>
            </form>
        </div>
    @endif
</div>
