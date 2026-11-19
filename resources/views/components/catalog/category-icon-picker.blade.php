@props(['categoryIconKey' => \App\Support\Catalog\CategoryIconOptions::DEFAULT])

@php
    use App\Support\Catalog\CategoryIconOptions;
    $iconKeys = CategoryIconOptions::keys();
    $selected = CategoryIconOptions::normalize($categoryIconKey);
@endphp

<fieldset
    class="catalog-category-icon-picker admin-modal-span-3 col-span-full min-w-0"
    data-testid="catalog-category-icon-picker"
>
    <legend class="form-field__label type-label mb-2">{{ __('catalog.category_icon') }}</legend>
    <div class="catalog-category-icon-picker__grid grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
        @foreach ($iconKeys as $iconKey)
            <label @class([
                'catalog-category-icon-picker__option flex min-h-11 cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border border-[var(--app-border)] p-2 text-center text-xs leading-tight transition',
                'catalog-category-icon-picker__option--active border-[var(--app-accent)] bg-[var(--app-accent-soft)] text-[var(--app-accent)]' => $selected === $iconKey,
            ])>
                <input
                    type="radio"
                    name="categoryIconKey"
                    value="{{ $iconKey }}"
                    wire:model.live="categoryIconKey"
                    class="sr-only"
                    @checked($selected === $iconKey)
                />
                <x-app.icon :name="$iconKey" class="h-6 w-6 shrink-0" />
                <span class="line-clamp-2">{{ __('catalog.category_icons.'.$iconKey) }}</span>
            </label>
        @endforeach
    </div>
</fieldset>