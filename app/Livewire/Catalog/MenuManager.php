<?php

namespace App\Livewire\Catalog;

use App\Enums\ProductItemType;
use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\ValidatesFieldsLive;
use App\Support\Validation\FieldValidationRules;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductIngredient;
use App\Models\ProductModifier;
use App\Support\Inventory\IngredientInventorySchema;
use App\Models\BusinessSetting;
use App\Support\Catalog\CatalogI18n;
use App\Support\Catalog\ProductCategorySchema;
use App\Support\Catalog\ProductStorefrontVisibility;
use App\Support\VatPricing;
use App\Business\BusinessContext;
use App\Services\Catalog\ProductImageService;
use App\Support\Upload\ProductImageConstraints;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Livewire\Concerns\ResolvesBusinessContext;
use Livewire\WithFileUploads;

#[Layout('layouts.pos')]
class MenuManager extends Component
{
    use ResolvesBusinessContext;
    use AuthorizesRequests;
    use DispatchesToasts;
    use ValidatesFieldsLive;
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

        $tab = request()->query('tab');
        if (is_string($tab) && in_array($tab, ['categories', 'products', 'modifiers'], true)) {
            $this->tab = $tab;
        }

        if (request()->query('create') === 'product' && auth('staff')->user()?->can('staff.manage')) {
            $this->tab = 'products';
            $this->startProduct();
        }
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['categories', 'products', 'modifiers'], true)) {
            $this->tab = $tab;
        }
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

    public function saveProduct(ProductImageService $images): void
    {
        $this->authorizeCatalogWrite();
        $rules = [
            'productSku' => ['nullable', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($this->editingProductId)],
            'productNameEn' => ['required', 'string', 'max:160'],
            'productNameAr' => ['required', 'string', 'max:160'],
            'productNameBn' => ['nullable', 'string', 'max:160'],
            'productDescriptionEn' => ['nullable', 'string', 'max:2000'],
            'productDescriptionAr' => ['nullable', 'string', 'max:2000'],
            'productDescriptionBn' => ['nullable', 'string', 'max:2000'],
            'productCategoryId' => ['nullable', 'integer'],
            'productPriceInput' => ['required', 'numeric', 'min:0'],
            'productVatRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'productActive' => ['boolean'],
            'productShowOnHomepage' => ['boolean'],
            'productShowOnMenu' => ['boolean'],
            'productModifierIds' => ['array'],
            'productItemType' => ['required', Rule::in(array_map(fn (ProductItemType $t) => $t->value, ProductItemType::cases()))],
        ];
        if ($this->productImageUpload !== null) {
            $rules['productImageUpload'] = ProductImageConstraints::componentRules();
        }
        $validated = $this->validate($rules, ProductImageConstraints::validationMessages());

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
                $this->toastError($e->getMessage(), persistent: true);

                return;
            }
        } elseif ($this->productImageRemove && filled($previousImage)) {
            $images->deleteIfManaged($previousImage);
        }

        $payload = [
            'sku' => $validated['productSku'] ?: null,
            'name_en' => $validated['productNameEn'],
            'name_ar' => $validated['productNameAr'],
            'product_category_id' => $validated['productCategoryId'] ?: null,
            'image_url' => $imageUrl,
            'price_ex_vat' => $priceEx,
            'vat_rate' => (float) $validated['productVatRate'],
            'is_active' => $validated['productActive'],
            'item_type' => $validated['productItemType'],
        ];

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

        if ($this->editingProductId) {
            $product = $this->productQuery()->findOrFail($this->editingProductId);
            $product->update($payload);
            $product->modifiers()->sync($validated['productModifierIds']);
            $this->toastSuccess(__('catalog.saved_product'));
        } else {
            $product = $this->productQuery()->create(array_merge(ProductStorefrontVisibility::defaultsForNewProduct(), $payload));
            $product->modifiers()->sync($validated['productModifierIds']);
            $this->toastSuccess(__('catalog.created_product'));
        }

        $this->productImageUpload = null;
        $this->showProductForm = false;
        $this->resetProductForm();
    }

    public function deleteProduct(int $id): void
    {
        $this->authorizeCatalogWrite();
        $this->productQuery()->findOrFail($id)->delete();
        $this->toastSuccess(__('catalog.deleted_product'));
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
            'canWrite' => auth('staff')->user()?->can('staff.manage') ?? false,
            'storefrontVisibilityReady' => ProductStorefrontVisibility::columnsExist()
                && ProductStorefrontVisibility::catalogUiEnabled(),
            'catalogI18nReady' => CatalogI18n::columnsExist(),
            'categoryBanglaFieldsReady' => CatalogI18n::categoryBanglaFieldsReady(),
            'categoryDescriptionFieldsReady' => CatalogI18n::categoryDescriptionFieldsReady(),
            'inventoryReady' => IngredientInventorySchema::productIngredientsReady(),
            'ingredientOptions' => IngredientInventorySchema::ingredientsReady()
                ? Ingredient::query()->whereNull('branch_id')->orderedForDisplay()->get()
                : collect(),
        ]);
    }

    private function authorizeCatalogWrite(): void
    {
        abort_unless(auth('staff')->user()?->can('staff.manage'), 403);
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
