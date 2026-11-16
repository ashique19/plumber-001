<?php

namespace App\Livewire\Catalog;

use App\Business\BusinessContext;
use App\Enums\ProductItemType;
use App\Enums\VatZatcaCategory;
use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\ManagesCatalogUnitsAndSizes;
use App\Livewire\Concerns\ResolvesBusinessContext;
use App\Livewire\Concerns\ValidatesFieldsLive;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductIngredient;
use App\Models\ProductModifier;
use App\Models\ProductSize;
use App\Models\ProductUnit;
use App\Services\Catalog\ProductImageService;
use App\Services\Inventory\ProductStockService;
use App\Support\Catalog\CatalogI18n;
use App\Support\Catalog\CatalogUnitsSizesSchema;
use App\Support\Catalog\ProductReferenceGuard;
use App\Support\Catalog\ProductSkuGenerator;
use App\Support\Catalog\ProductCategorySchema;
use App\Support\Catalog\ProductStorefrontVisibility;
use App\Support\Inventory\IngredientInventorySchema;
use App\Support\Inventory\ProductStockSchema;
use App\Support\Upload\ProductImageConstraints;
use App\Support\VatPricing;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.pos')]
class MenuManager extends Component
{
    use AuthorizesRequests;
    use DispatchesToasts;
    use ResolvesBusinessContext;
    use ValidatesFieldsLive;
    use ManagesCatalogUnitsAndSizes;
    use WithFileUploads;

    public string $tab = 'products';

    /** @var 'ex'|'inc' */
    public string $priceMode = 'ex';

    public bool $showCategoryForm = false;

    public ?int $editingCategoryId = null;

    public string $categoryNameEn = '';

    public string $categoryNameAr = '';

    public string $categoryNameBn = '';

    public string $categoryDescriptionEn = '';

    public string $categoryDescriptionAr = '';

    public string $categoryDescriptionBn = '';

    public string $categoryIconKey = 'cup';

    public bool $categoryActive = true;

    public bool $showProductForm = false;

    public ?int $editingProductId = null;

    public string $productSku = '';

    public string $productNameEn = '';

    public string $productNameAr = '';

    public string $productNameBn = '';

    public string $productDescriptionEn = '';

    public string $productDescriptionAr = '';

    public string $productDescriptionBn = '';

    public ?int $productCategoryId = null;

    public $productImageUpload = null;

    public ?string $productExistingImage = null;

    public bool $productImageRemove = false;

    public string $productPriceInput = '';

    public float $productVatRate = 15.0;

    public bool $productActive = true;

    public bool $productShowOnHomepage = false;

    public bool $productShowOnMenu = true;

    public bool $productTrackStock = false;

    public string $productLowStockThreshold = '0';

    public string $productCostInput = '';

    public string $productOpeningStock = '';

    public ?int $productUnitId = null;

    public ?int $productSizeId = null;

    public string $productVatZatcaCategory = 'S';

    /** @var 'basic'|'pricing'|'stock' */
    public string $productFormTab = 'basic';

    /** @var 'item'|'service'|'part' */
    public string $productItemType = 'item';

    /** @var list<int> */
    public array $productModifierIds = [];

    public bool $showModifierForm = false;

    public ?int $editingModifierId = null;

    public string $modifierNameEn = '';

    public string $modifierNameAr = '';

    public string $modifierPriceInput = '';

    public float $modifierVatRate = 15.0;

    public bool $modifierActive = true;

    public bool $showIngredientsModal = false;

    public ?int $ingredientsProductId = null;

    /** @var list<array{ingredient_id: int|string, quantity_per_unit: string}> */
    public array $ingredientLines = [];

    public string $quickIngredientNameEn = '';

    public string $quickIngredientUnit = 'g';

    public function mount(): void
    {
        $this->resolveBusiness();
        BusinessContext::set($this->business);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->business->id);

        $tab = request()->query('tab');
        if (is_string($tab) && in_array($tab, ['categories', 'products', 'modifiers', 'units', 'sizes'], true)) {
            $this->tab = $tab;
        }

        if (request()->query('create') === 'product' && $this->staffCanManageCatalog()) {
            $this->tab = 'products';
            $this->startProduct();
        }
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['categories', 'products', 'modifiers', 'units', 'sizes'], true)) {
            $this->tab = $tab;
        }
    }

    public function updatedProductVatZatcaCategory(string $value): void
    {
        $category = VatZatcaCategory::tryFrom($value) ?? VatZatcaCategory::Standard;
        $this->productVatRate = $category->vatRateDefault();
    }

    public function setProductFormTab(string $tab): void
    {
        if (in_array($tab, ['basic', 'pricing', 'stock'], true)) {
            $this->productFormTab = $tab;
        }
    }

    /**
     * @return array{basic: int, pricing: int, stock: int}
     */
    public function productFormTabErrorCounts(): array
    {
        $counts = ['basic' => 0, 'pricing' => 0, 'stock' => 0];

        foreach ($this->getErrorBag()->getMessages() as $field => $messages) {
            $tab = $this->productFormTabForField((string) $field);
            if ($tab !== null) {
                $counts[$tab] += count($messages);
            }
        }

        return $counts;
    }

    public function startCategory(?int $id = null): void
    {
        $this->authorizeCatalogWrite();
        $this->resetCategoryForm();
        $this->showCategoryForm = true;
        if ($id !== null) {
            $cat = $this->categoryQuery()->findOrFail($id);
            $this->editingCategoryId = $cat->id;
            $this->categoryNameEn = ProductCategorySchema::hasNameEnColumn()
                ? (string) $cat->name_en
                : (string) ($cat->name ?? '');
            $this->categoryNameAr = ProductCategorySchema::hasNameArColumn()
                ? (string) ($cat->name_ar ?? '')
                : '';
            $this->categoryNameBn = ProductCategorySchema::hasNameBnColumn() ? (string) ($cat->name_bn ?? '') : '';
            $this->categoryDescriptionEn = ProductCategorySchema::hasDescriptionEnColumn() ? (string) ($cat->description_en ?? '') : '';
            $this->categoryDescriptionAr = ProductCategorySchema::hasDescriptionArColumn() ? (string) ($cat->description_ar ?? '') : '';
            $this->categoryDescriptionBn = ProductCategorySchema::hasDescriptionBnColumn() ? (string) ($cat->description_bn ?? '') : '';
            $this->categoryIconKey = (string) ($cat->icon_key ?: 'cup');
            $this->categoryActive = (bool) $cat->is_active;
        }
    }

    public function saveCategory(): void
    {
        $this->authorizeCatalogWrite();
        $validated = $this->validate([
            'categoryNameEn' => ['required', 'string', 'max:120'],
            'categoryNameAr' => ['nullable', 'string', 'max:120'],
            'categoryNameBn' => ['nullable', 'string', 'max:120'],
            'categoryDescriptionEn' => ['nullable', 'string', 'max:2000'],
            'categoryDescriptionAr' => ['nullable', 'string', 'max:2000'],
            'categoryDescriptionBn' => ['nullable', 'string', 'max:2000'],
            'categoryIconKey' => ['nullable', 'string', 'max:40'],
            'categoryActive' => ['boolean'],
        ]);

        $payload = ProductCategorySchema::mutationPayload($validated);

        if ($this->editingCategoryId) {
            $cat = $this->categoryQuery()->findOrFail($this->editingCategoryId);
            $cat->update($payload);
            $this->toastSuccess(__('catalog.saved_category'));
        } else {
            $this->categoryQuery()->create($payload);
            $this->toastSuccess(__('catalog.created_category'));
        }

        $this->showCategoryForm = false;
        $this->resetCategoryForm();
    }

    public function deleteCategory(int $id): void
    {
        $this->authorizeCatalogWrite();
        $this->categoryQuery()->findOrFail($id)->delete();
        $this->toastSuccess(__('catalog.deleted_category'));
    }

    public function startProduct(?int $id = null): void
    {
        $this->authorizeCatalogWrite();
        $this->resetProductForm();
        $this->showProductForm = true;
        if ($id !== null) {
            $product = $this->productQuery()->with('modifiers')->findOrFail($id);
            $this->editingProductId = $product->id;
            $this->productSku = (string) $product->sku;
            $this->productNameEn = $product->name_en;
            $this->productNameAr = (string) $product->name_ar;
            $this->productNameBn = CatalogI18n::columnsExist() ? (string) ($product->name_bn ?? '') : '';
            $this->productDescriptionEn = CatalogI18n::columnsExist() ? (string) ($product->description_en ?? '') : '';
            $this->productDescriptionAr = CatalogI18n::columnsExist() ? (string) ($product->description_ar ?? '') : '';
            $this->productDescriptionBn = CatalogI18n::columnsExist() ? (string) ($product->description_bn ?? '') : '';
            $this->productCategoryId = $product->product_category_id;
            $this->productExistingImage = filled($product->image_url) ? (string) $product->image_url : null;
            $this->productImageRemove = false;
            $this->productImageUpload = null;
            $this->productVatRate = (float) $product->vat_rate;
            $this->productActive = (bool) $product->is_active;
            $this->productShowOnHomepage = ProductStorefrontVisibility::columnsExist()
                ? (bool) $product->show_on_homepage
                : false;
            $this->productShowOnMenu = ProductStorefrontVisibility::columnsExist()
                ? (bool) $product->show_on_menu
                : true;
            $this->productModifierIds = $product->modifiers->pluck('id')->all();
            $this->productPriceInput = $this->formatPriceForMode((float) $product->price_ex_vat, $this->productVatRate);
            $this->productItemType = $product->itemType()->value;
            if (ProductStockSchema::ready()) {
                $this->productTrackStock = (bool) $product->track_stock;
                $this->productLowStockThreshold = (string) ($product->low_stock_threshold ?? 0);
                $this->productCostInput = number_format((float) $product->cost_ex_vat, 2, '.', '');
                $this->productOpeningStock = '';
            }
            if (CatalogUnitsSizesSchema::ready()) {
                $this->productUnitId = $product->product_unit_id;
                $this->productSizeId = $product->product_size_id;
                $this->productVatZatcaCategory = $product->vatZatcaCategory()->value;
            }
        } elseif (CatalogUnitsSizesSchema::ready()) {
            $this->productSku = ProductSkuGenerator::next();
            $this->productUnitId = $this->defaultProductUnitId();
            $this->productVatZatcaCategory = VatZatcaCategory::Standard->value;
        }
    }

    public function updatedProductImageUpload(): void
    {
        $this->resetValidation('productImageUpload');
        $this->productImageRemove = false;

        if ($this->productImageUpload === null) {
            return;
        }

        $path = $this->productImageUpload->getRealPath();
        if (! is_string($path) || ! is_readable($path)) {
            $this->rejectProductImageUpload(__('catalog.product_image.errors.processing_failed'));

            return;
        }

        $size = @getimagesize($path);
        if ($size === false) {
            $this->rejectProductImageUpload(__('catalog.product_image.errors.wrong_type'));

            return;
        }

        [$width, $height] = $size;
        if ($width < ProductImageConstraints::MIN_WIDTH || $height < ProductImageConstraints::MIN_HEIGHT) {
            $this->rejectProductImageUpload(__('catalog.product_image.errors.too_small', [
                'min' => ProductImageConstraints::MIN_WIDTH,
            ]));
        }
    }

    public function removeProductImage(): void
    {
        $this->authorizeCatalogWrite();
        $this->productImageUpload = null;
        $this->productImageRemove = true;
        $this->resetValidation('productImageUpload');
        $this->dispatch('catalog-product-image-cleared');
    }

    public function saveProduct(ProductImageService $images, ProductStockService $stock): void
    {
        $this->authorizeCatalogWrite();
        $rules = [
            'productNameEn' => ['required', 'string', 'max:160'],
            'productNameAr' => ['required', 'string', 'max:160'],
            'productDescriptionEn' => ['nullable', 'string', 'max:2000'],
            'productDescriptionAr' => ['nullable', 'string', 'max:2000'],
            'productDescriptionBn' => ['nullable', 'string', 'max:2000'],
            'productPriceInput' => ['required', 'numeric', 'min:0'],
            'productVatRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'productActive' => ['boolean'],
            'productShowOnHomepage' => ['boolean'],
            'productShowOnMenu' => ['boolean'],
            'productModifierIds' => ['array'],
            'productItemType' => ['required', Rule::in(array_map(fn (ProductItemType $t) => $t->value, ProductItemType::cases()))],
        ];
        if (CatalogI18n::columnsExist()) {
            $rules['productNameBn'] = ['required', 'string', 'max:160'];
        } else {
            $rules['productNameBn'] = ['nullable', 'string', 'max:160'];
        }

        if (CatalogUnitsSizesSchema::ready()) {
            $rules['productCategoryId'] = ['required', 'integer', 'exists:product_categories,id'];
            $rules['productUnitId'] = ['required', 'integer', 'exists:product_units,id'];
            $rules['productSizeId'] = ['nullable', 'integer', 'exists:product_sizes,id'];
            $rules['productVatZatcaCategory'] = ['required', Rule::in(VatZatcaCategory::values())];
        } else {
            $rules['productCategoryId'] = ['nullable', 'integer'];
            $rules['productSku'] = ['nullable', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($this->editingProductId)];
        }

        if (ProductStockSchema::ready()) {
            $rules['productTrackStock'] = ['boolean'];
            $rules['productLowStockThreshold'] = ['nullable', 'numeric', 'min:0'];
            $rules['productCostInput'] = ['required', 'numeric', 'min:0'];
            if (! $this->editingProductId) {
                $rules['productOpeningStock'] = [Rule::requiredIf(fn (): bool => $this->productTrackStock), 'nullable', 'numeric', 'gt:0'];
            }
        }
        if ($this->productImageUpload !== null) {
            $rules['productImageUpload'] = ProductImageConstraints::componentRules();
        }

        try {
            $validated = $this->validate($rules, ProductImageConstraints::validationMessages());
        } catch (ValidationException $e) {
            $this->focusProductFormTabForErrors(array_keys($e->validator->errors()->messages()));
            throw $e;
        }

        $priceEx = $this->priceMode === 'inc'
            ? VatPricing::exFromInc((float) $validated['productPriceInput'], (float) $validated['productVatRate'])
            : (float) $validated['productPriceInput'];

        $previousImage = $this->editingProductId
            ? (string) ($this->productQuery()->find($this->editingProductId)?->image_url ?? '')
            : '';

        $imageUrl = $this->productImageRemove ? null : ($this->productExistingImage ?: null);
        if ($this->productImageUpload !== null) {
            try {
                $imageUrl = $images->store($this->business, $this->productImageUpload, $previousImage ?: $this->productExistingImage);
            } catch (\InvalidArgumentException $e) {
                $this->addError('productImageUpload', $e->getMessage());
                $this->productFormTab = 'basic';
                $this->toastError($e->getMessage(), persistent: true);

                return;
            }
        } elseif ($this->productImageRemove && filled($previousImage)) {
            $images->deleteIfManaged($previousImage);
        }

        $sku = $this->editingProductId
            ? (string) ($this->productQuery()->find($this->editingProductId)?->sku ?? $this->productSku)
            : (CatalogUnitsSizesSchema::ready() ? ProductSkuGenerator::next() : ($validated['productSku'] ?? null));

        $payload = [
            'sku' => $sku ?: null,
            'name_en' => $validated['productNameEn'],
            'name_ar' => $validated['productNameAr'],
            'product_category_id' => $validated['productCategoryId'] ?: null,
            'image_url' => $imageUrl,
            'price_ex_vat' => $priceEx,
            'vat_rate' => (float) $validated['productVatRate'],
            'is_active' => $validated['productActive'],
            'item_type' => $validated['productItemType'],
        ];

        if (CatalogUnitsSizesSchema::ready()) {
            $payload['product_unit_id'] = (int) $validated['productUnitId'];
            $payload['product_size_id'] = $validated['productSizeId'] ?? null;
            $payload['vat_zatca_category'] = $validated['productVatZatcaCategory'];
            $payload['name_i18n_review'] = false;
        }

        if (ProductStorefrontVisibility::columnsExist() && ProductStorefrontVisibility::catalogUiEnabled()) {
            $payload['show_on_homepage'] = $validated['productShowOnHomepage'];
            $payload['show_on_menu'] = $validated['productShowOnMenu'];
        }

        if (CatalogI18n::columnsExist()) {
            $payload['name_bn'] = $validated['productNameBn'] ?: null;
            $payload['description_en'] = $validated['productDescriptionEn'] ?: null;
            $payload['description_ar'] = $validated['productDescriptionAr'] ?: null;
            $payload['description_bn'] = $validated['productDescriptionBn'] ?: null;
        }

        if (ProductStockSchema::ready()) {
            $payload['track_stock'] = (bool) ($validated['productTrackStock'] ?? false);
            $payload['low_stock_threshold'] = round((float) ($validated['productLowStockThreshold'] ?? 0), 3);
            $payload['cost_ex_vat'] = round((float) ($validated['productCostInput'] ?? 0), 2);
        }

        if ($this->editingProductId) {
            $product = $this->productQuery()->findOrFail($this->editingProductId);
            $product->update($payload);
            $product->modifiers()->sync($validated['productModifierIds']);
            $this->toastSuccess(__('catalog.saved_product'));
        } else {
            $product = $this->productQuery()->create(array_merge(ProductStorefrontVisibility::defaultsForNewProduct(), $payload));
            $product->modifiers()->sync($validated['productModifierIds']);
            if (ProductStockSchema::ready() && $product->track_stock) {
                $opening = (float) ($validated['productOpeningStock'] ?? 0);
                $stock->recordOpeningStock(
                    $product,
                    $opening,
                    (float) $payload['cost_ex_vat'],
                    null,
                    auth('staff')->id(),
                );
            }
            $this->toastSuccess(__('catalog.created_product'));
        }

        $this->productImageUpload = null;
        $this->showProductForm = false;
        $this->resetProductForm();
    }

    public function deleteProduct(int $id): void
    {
        $this->authorizeCatalogWrite();
        $product = $this->productQuery()->findOrFail($id);

        if (CatalogUnitsSizesSchema::ready() && ProductReferenceGuard::isReferenced($product)) {
            $product->update([
                'is_active' => false,
                'archived_at' => now(),
            ]);
            $this->toastSuccess(__('catalog.archived_product_referenced'));
        } else {
            $product->delete();
            $this->toastSuccess(__('catalog.deleted_product'));
        }
    }

    public function toggleProductShowOnHomepage(int $id): void
    {
        $this->authorizeCatalogWrite();
        if (! ProductStorefrontVisibility::columnsExist() || ! ProductStorefrontVisibility::catalogUiEnabled()) {
            return;
        }
        $product = $this->productQuery()->findOrFail($id);
        $product->update(['show_on_homepage' => ! $product->show_on_homepage]);
        $this->toastSuccess(__('catalog.saved_product'));
    }

    public function toggleProductShowOnMenu(int $id): void
    {
        $this->authorizeCatalogWrite();
        if (! ProductStorefrontVisibility::columnsExist() || ! ProductStorefrontVisibility::catalogUiEnabled()) {
            return;
        }
        $product = $this->productQuery()->findOrFail($id);
        $product->update(['show_on_menu' => ! $product->show_on_menu]);
        $this->toastSuccess(__('catalog.saved_product'));
    }

    public function startModifier(?int $id = null): void
    {
        $this->authorizeCatalogWrite();
        $this->resetModifierForm();
        $this->showModifierForm = true;
        if ($id !== null) {
            $mod = $this->modifierQuery()->findOrFail($id);
            $this->editingModifierId = $mod->id;
            $this->modifierNameEn = $mod->name_en;
            $this->modifierNameAr = (string) $mod->name_ar;
            $this->modifierVatRate = (float) $mod->vat_rate;
            $this->modifierActive = (bool) $mod->is_active;
            $this->modifierPriceInput = $this->formatPriceForMode((float) $mod->price_ex_vat, $this->modifierVatRate);
        }
    }

    public function saveModifier(): void
    {
        $this->authorizeCatalogWrite();
        $validated = $this->validate([
            'modifierNameEn' => ['required', 'string', 'max:120'],
            'modifierNameAr' => ['nullable', 'string', 'max:120'],
            'modifierPriceInput' => ['required', 'numeric', 'min:0'],
            'modifierVatRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'modifierActive' => ['boolean'],
        ]);

        $priceEx = $this->priceMode === 'inc'
            ? VatPricing::exFromInc((float) $validated['modifierPriceInput'], (float) $validated['modifierVatRate'])
            : (float) $validated['modifierPriceInput'];

        $payload = [
            'name_en' => $validated['modifierNameEn'],
            'name_ar' => $validated['modifierNameAr'] ?: null,
            'price_ex_vat' => $priceEx,
            'vat_rate' => (float) $validated['modifierVatRate'],
            'is_active' => $validated['modifierActive'],
        ];

        if ($this->editingModifierId) {
            $this->modifierQuery()->findOrFail($this->editingModifierId)->update($payload);
            $this->toastSuccess(__('catalog.saved_modifier'));
        } else {
            $this->modifierQuery()->create($payload);
            $this->toastSuccess(__('catalog.created_modifier'));
        }

        $this->showModifierForm = false;
        $this->resetModifierForm();
    }

    public function deleteModifier(int $id): void
    {
        $this->authorizeCatalogWrite();
        $this->modifierQuery()->findOrFail($id)->delete();
        $this->toastSuccess(__('catalog.deleted_modifier'));
    }

    public function openProductIngredients(int $productId): void
    {
        $this->authorizeCatalogWrite();
        if (! IngredientInventorySchema::productIngredientsReady()) {
            $this->toastError(__('inventory.error_missing_ingredient'));

            return;
        }

        $this->ingredientsProductId = $productId;
        $rows = ProductIngredient::query()
            ->where('product_id', $productId)
            ->get();
        $this->ingredientLines = $rows->map(fn (ProductIngredient $row) => [
            'ingredient_id' => $row->ingredient_id,
            'quantity_per_unit' => (string) $row->quantity_per_unit,
        ])->all() ?: [['ingredient_id' => '', 'quantity_per_unit' => '']];
        $this->showIngredientsModal = true;
    }

    public function addIngredientLine(): void
    {
        $this->ingredientLines[] = ['ingredient_id' => '', 'quantity_per_unit' => ''];
    }

    public function removeIngredientLine(int $index): void
    {
        unset($this->ingredientLines[$index]);
        $this->ingredientLines = array_values($this->ingredientLines);
    }

    public function quickCreateIngredient(): void
    {
        $this->authorizeCatalogWrite();
        if (! IngredientInventorySchema::ingredientsReady()) {
            return;
        }

        $this->validate([
            'quickIngredientNameEn' => ['required', 'string', 'max:160'],
            'quickIngredientUnit' => ['required', 'string', 'max:16'],
        ]);

        $ing = Ingredient::query()->create([
            'branch_id' => null,
            'name_en' => $this->quickIngredientNameEn,
            'unit' => $this->quickIngredientUnit,
            'quantity_on_hand' => 0,
            'reorder_level' => 0,
            'low_stock_threshold' => 0,
            'is_active' => true,
        ]);

        $this->ingredientLines[] = [
            'ingredient_id' => $ing->id,
            'quantity_per_unit' => '1',
        ];
        $this->quickIngredientNameEn = '';
        $this->toastSuccess(__('inventory.created'));
    }

    public function saveProductIngredients(): void
    {
        $this->authorizeCatalogWrite();
        if (! IngredientInventorySchema::productIngredientsReady()) {
            return;
        }

        $validated = $this->validate([
            'ingredientLines' => ['array'],
            'ingredientLines.*.ingredient_id' => ['required', 'integer'],
            'ingredientLines.*.quantity_per_unit' => ['required', 'numeric', 'gt:0'],
        ]);

        $productId = (int) $this->ingredientsProductId;
        $this->productQuery()->findOrFail($productId);

        ProductIngredient::query()->where('product_id', $productId)->delete();
        foreach ($validated['ingredientLines'] as $line) {
            ProductIngredient::query()->create([
                'product_id' => $productId,
                'ingredient_id' => (int) $line['ingredient_id'],
                'quantity_per_unit' => $line['quantity_per_unit'],
            ]);
        }

        $this->showIngredientsModal = false;
        $this->toastSuccess(__('inventory.saved'));
    }

    public function render(): View
    {
        $nameCol = ProductCategorySchema::nameOrderColumn();
        $categories = $this->categoryQuery()->orderBy('sort_order')->orderBy($nameCol)->get();
        $products = $this->productQuery()->with('category')->orderBy('sort_order')->orderBy('name_en')->get();
        $modifiers = $this->modifierQuery()->orderBy('name_en')->get();

        return view('livewire.catalog.menu-manager', [
            'categories' => $categories,
            'products' => $products,
            'modifiers' => $modifiers,
            'canWrite' => $this->staffCanManageCatalog(),
            'storefrontVisibilityReady' => ProductStorefrontVisibility::columnsExist()
                && ProductStorefrontVisibility::catalogUiEnabled(),
            'catalogI18nReady' => CatalogI18n::columnsExist(),
            'categoryBanglaFieldsReady' => CatalogI18n::categoryBanglaFieldsReady(),
            'categoryDescriptionFieldsReady' => CatalogI18n::categoryDescriptionFieldsReady(),
            'inventoryReady' => IngredientInventorySchema::productIngredientsReady(),
            'productStockReady' => ProductStockSchema::ready(),
            'ingredientOptions' => IngredientInventorySchema::ingredientsReady()
                ? Ingredient::query()->whereNull('branch_id')->orderedForDisplay()->get()
                : collect(),
            'catalogUnitsSizesReady' => CatalogUnitsSizesSchema::ready(),
            'catalogUnits' => CatalogUnitsSizesSchema::ready()
                ? ProductUnit::query()->orderBy('code')->get()
                : collect(),
            'catalogSizes' => CatalogUnitsSizesSchema::ready()
                ? ProductSize::query()->orderBy('name')->get()
                : collect(),
            'activeUnitsForPicker' => CatalogUnitsSizesSchema::ready()
                ? ProductUnit::query()->where('is_active', true)->orderBy('name_en')->get()
                : collect(),
            'activeSizesForPicker' => CatalogUnitsSizesSchema::ready()
                ? ProductSize::query()->where('is_active', true)->orderBy('name')->get()
                : collect(),
            'vatZatcaCategoryOptions' => VatZatcaCategory::cases(),
            'productFormTabErrorCounts' => $this->productFormTabErrorCounts(),
        ]);
    }

    private function authorizeCatalogWrite(): void
    {
        abort_unless($this->staffCanManageCatalog(), 403);
    }

    private function staffCanManageCatalog(): bool
    {
        return auth('staff')->user()?->can('staff.manage') ?? false;
    }

    private function paginatedProducts()
    {
        $query = $this->productQuery()->with(['category', 'unit', 'size']);

        $term = trim($this->productSearch);
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($q) use ($like) {
                $q->where('name_en', 'like', $like)
                    ->orWhere('name_ar', 'like', $like)
                    ->orWhere('sku', 'like', $like);
                if (CatalogI18n::columnsExist()) {
                    $q->orWhere('name_bn', 'like', $like);
                }
            });
        }

        if ($this->productCategoryFilter) {
            $query->where('product_category_id', $this->productCategoryFilter);
        }

        if ($this->productKind !== 'all') {
            $query->where('item_type', $this->productKind);
        }

        match ($this->productSort) {
            'name_desc' => $query->orderByDesc('name_en'),
            'price_asc' => $query->orderBy('price_ex_vat'),
            'price_desc' => $query->orderByDesc('price_ex_vat'),
            'newest' => $query->orderByDesc('id'),
            default => $query->orderBy('sort_order')->orderBy('name_en'),
        };

        return $query->paginate(24);
    }

    private function duplicateSku(?string $sku): ?string
    {
        if ($sku === null || trim($sku) === '') {
            return null;
        }

        $base = mb_substr($sku, 0, 58).'-C';
        $candidate = $base;
        $n = 2;
        while ($this->productQuery()->where('sku', $candidate)->exists()) {
            $candidate = $base.$n;
            $n++;
        }

        return $candidate;
    }

    private function formatPriceForMode(float $exVat, float $vatRate): string
    {
        $value = $this->priceMode === 'inc'
            ? VatPricing::incFromEx($exVat, $vatRate)
            : $exVat;

        return number_format($value, 2, '.', '');
    }

    private function categoryQuery()
    {
        return ProductCategory::query();
    }

    private function productQuery()
    {
        return Product::query();
    }

    private function modifierQuery()
    {
        return ProductModifier::query();
    }

    private function resetCategoryForm(): void
    {
        $this->editingCategoryId = null;
        $this->categoryNameEn = '';
        $this->categoryNameAr = '';
        $this->categoryNameBn = '';
        $this->categoryDescriptionEn = '';
        $this->categoryDescriptionAr = '';
        $this->categoryDescriptionBn = '';
        $this->categoryIconKey = 'cup';
        $this->categoryActive = true;
    }

    private function resetProductForm(): void
    {
        $this->editingProductId = null;
        $this->productSku = '';
        $this->productNameEn = '';
        $this->productNameAr = '';
        $this->productNameBn = '';
        $this->productDescriptionEn = '';
        $this->productDescriptionAr = '';
        $this->productDescriptionBn = '';
        $this->productCategoryId = null;
        $this->productImageUpload = null;
        $this->productExistingImage = null;
        $this->productImageRemove = false;
        $this->productPriceInput = '';
        $this->productVatRate = 15.0;
        $this->productActive = true;
        $this->productShowOnHomepage = false;
        $this->productShowOnMenu = true;
        $this->productItemType = ProductItemType::Item->value;
        $this->productModifierIds = [];
        $this->productTrackStock = false;
        $this->productLowStockThreshold = '0';
        $this->productCostInput = '';
        $this->productOpeningStock = '';
        $this->productUnitId = CatalogUnitsSizesSchema::ready() ? $this->defaultProductUnitId() : null;
        $this->productSizeId = null;
        $this->productVatZatcaCategory = VatZatcaCategory::Standard->value;
        $this->productFormTab = 'basic';
    }

    /**
     * @param  list<string>  $errorKeys
     */
    private function focusProductFormTabForErrors(array $errorKeys): void
    {
        foreach (['basic', 'pricing', 'stock'] as $tab) {
            foreach ($errorKeys as $key) {
                if ($this->productFormTabForField($key) === $tab) {
                    $this->productFormTab = $tab;

                    return;
                }
            }
        }
    }

    private function productFormTabForField(string $field): ?string
    {
        $field = explode('.', $field)[0];

        $basic = [
            'productNameEn', 'productNameAr', 'productNameBn',
            'productDescriptionEn', 'productDescriptionAr', 'productDescriptionBn',
            'productCategoryId', 'productUnitId', 'productSizeId', 'productSku',
            'productImageUpload', 'productItemType', 'productModifierIds',
            'productActive', 'productShowOnHomepage', 'productShowOnMenu',
        ];
        $pricing = ['productCostInput', 'productPriceInput', 'productVatRate', 'productVatZatcaCategory'];
        $stock = ['productTrackStock', 'productOpeningStock', 'productLowStockThreshold'];

        if (in_array($field, $basic, true)) {
            return 'basic';
        }
        if (in_array($field, $pricing, true)) {
            return 'pricing';
        }
        if (in_array($field, $stock, true)) {
            return 'stock';
        }

        return null;
    }

    private function defaultProductUnitId(): ?int
    {
        if (! CatalogUnitsSizesSchema::ready()) {
            return null;
        }

        return ProductUnit::query()->where('code', 'PCS')->value('id');
    }

    private function rejectProductImageUpload(string $message): void
    {
        $this->addError('productImageUpload', $message);
        $this->productImageUpload = null;
    }

    private function resetModifierForm(): void
    {
        $this->editingModifierId = null;
        $this->modifierNameEn = '';
        $this->modifierNameAr = '';
        $this->modifierPriceInput = '';
        $this->modifierVatRate = 15.0;
        $this->modifierActive = true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function liveValidationRules(): array
    {
        return [
            'categoryNameEn' => ['required', 'string', 'max:120'],
            'categoryNameAr' => ['nullable', 'string', 'max:120'],
            'productNameEn' => ['required', 'string', 'max:160'],
            'productNameAr' => ['required', 'string', 'max:160'],
            'productPriceInput' => ['required', 'numeric', 'min:0'],
            'productVatRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'modifierNameEn' => ['required', 'string', 'max:160'],
            'modifierPriceInput' => ['required', 'numeric', 'min:0'],
            'modifierVatRate' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
