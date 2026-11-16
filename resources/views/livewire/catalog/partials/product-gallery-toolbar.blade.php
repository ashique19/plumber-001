@props([
    'categories',
])

@php
    $staffUserId = auth('staff')->id() ?? 0;
@endphp

<div
    class="catalog-product-toolbar mt-4 space-y-3"
    x-data="catalogProductGallery(@js($staffUserId))"
>
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        @if ($canWrite)
            <x-app.add-button type="button" variant="primary" wire:click="startProduct()" class="mb-0 w-full shrink-0 lg:w-auto">
                {{ __('catalog.add_product') }}
            </x-app.add-button>
        @endif

        <div class="flex flex-wrap items-center gap-2 lg:ms-auto" role="group" aria-label="{{ __('catalog.kind_filter_label') }}">
            @foreach (['all' => __('catalog.kind_all'), 'item' => __('catalog.kind_products'), 'service' => __('catalog.kind_services')] as $value => $label)
                <button
                    type="button"
                    wire:click="$set('productKind', '{{ $value }}')"
                    @class([
                        'app-chip',
                        'app-chip--active' => $productKind === $value,
                    ])
                >{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div class="app-admin-filter app-card flex flex-col gap-3 p-3 sm:flex-row sm:flex-wrap sm:items-end">
        <x-field.search
            class="min-w-0 flex-1 sm:min-w-[12rem]"
            :label="__('catalog.product_search')"
            name="productSearch"
            wire:model.live.debounce.300ms="productSearch"
            :placeholder="__('catalog.product_search_placeholder')"
            :clearable="trim($productSearch) !== ''"
        />

        <label class="form-field w-full min-w-0 sm:w-auto sm:max-w-[11rem]">
            <span class="form-field__label">{{ __('catalog.category') }}</span>
            <select wire:model.live="productCategoryFilter" class="form-field__control w-full">
                <option value="">{{ __('catalog.category_all') }}</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->displayName() }}</option>
                @endforeach
            </select>
        </label>

        <label class="form-field w-full min-w-0 sm:w-auto sm:max-w-[11rem]">
            <span class="form-field__label">{{ __('catalog.product_sort') }}</span>
            <select wire:model.live="productSort" class="form-field__control w-full">
                <option value="name_asc">{{ __('catalog.sort_name_asc') }}</option>
                <option value="name_desc">{{ __('catalog.sort_name_desc') }}</option>
                <option value="price_asc">{{ __('catalog.sort_price_asc') }}</option>
                <option value="price_desc">{{ __('catalog.sort_price_desc') }}</option>
                <option value="newest">{{ __('catalog.sort_newest') }}</option>
            </select>
        </label>

        <div class="flex w-full items-end justify-end gap-1 sm:ms-auto sm:w-auto" role="group" aria-label="{{ __('catalog.view_toggle_label') }}">
            <button
                type="button"
                class="catalog-view-toggle"
                :class="{ 'catalog-view-toggle--active': $wire.productsView === 'gallery' }"
                @click="setView('gallery')"
                aria-pressed="true"
                x-bind:aria-pressed="$wire.productsView === 'gallery'"
            >
                <x-app.icon name="grid" class="h-5 w-5" />
                <span class="sr-only">{{ __('catalog.view_gallery') }}</span>
            </button>
            <button
                type="button"
                class="catalog-view-toggle"
                :class="{ 'catalog-view-toggle--active': $wire.productsView === 'list' }"
                @click="setView('list')"
                x-bind:aria-pressed="$wire.productsView === 'list'"
            >
                <x-app.icon name="list" class="h-5 w-5" />
                <span class="sr-only">{{ __('catalog.view_list') }}</span>
            </button>
        </div>
    </div>
</div>