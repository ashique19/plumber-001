<?php

namespace App\Livewire\Concerns;

use App\Enums\ProductItemType;
use App\Models\Product;
use App\Models\BusinessSetting;
use App\Models\ProductUnit;
use App\Support\Catalog\QuickProductCategoryOptions;
use App\Support\Catalog\CatalogUnitsSizesSchema;
use App\Support\Catalog\ProductCostInput;
use App\Support\Catalog\ProductSkuGenerator;
use App\Support\Invoicing\DocumentCatalogLineMerge;
use App\Support\Invoicing\DocumentCreateUiSupport;
use App\Support\Invoicing\InvoicingCatalogLookup;
use App\Support\Inventory\ProductStockSchema;
use Illuminate\Support\Collection;
use App\Support\Catalog\CatalogI18n;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait ManagesPurchaseCatalogPicker
{
    use ManagesQuickProductFormTabs;
    use ManagesQuickProductImageUpload;
    public string $addLineProductSearch = '';

    public bool $showQuickProductModal = false;

    public string $quickProductNameEn = '';

    public string $quickProductNameAr = '';

    public string $quickProductPrice = '';

    public string $quickProductVat = '15';

    public string $quickProductItemType = 'item';

    public ?int $quickProductCategoryId = null;

    public bool $quickProductTrackStock = true;

    public string $quickProductCost = '';

    /**
     * @return Collection<int, Product>
     */
    public function purchaseProductSearchResults(): Collection
    {
        return InvoicingCatalogLookup::productsForAddLine($this->addLineProductSearch, 20)
            ->filter(fn (Product $product) => (bool) $product->track_stock);
    }

    public function canCreateProductsFromPurchase(): bool
    {
        $user = auth('staff')->user();

        return $user !== null
            && $user->can('inventory.view')
            && $user->can('staff.manage');
    }

    public function addPurchaseLineFromProduct(int $productId): void
    {
        $product = Product::query()
            ->where('is_active', true)
            ->where('track_stock', true)
            ->find($productId);

        if ($product === null) {
            return;
        }

        $existingIndex = DocumentCatalogLineMerge::findPurchaseLineIndex($this->lines, $product);
        if ($existingIndex !== null) {
            DocumentCatalogLineMerge::bumpLineQuantity($this->lines, $existingIndex, 1.0);
            $this->addLineProductSearch = '';
            $this->dispatch('document-line-merged', lineIndex: $existingIndex);
            $this->toastInfo(__('invoices.catalog_product_qty_bumped', [
                'name' => $product->displayName(),
                'qty' => $this->lines[$existingIndex]['quantity'],
            ]));

            return;
        }

        foreach ($this->lines as $index => $line) {
            if (! filled($line['product_id'] ?? null)) {
                $this->fillPurchaseLineFromProduct($index, $product);
                $this->addLineProductSearch = '';
                $this->dispatch('document-line-added', lineIndex: $index);

                return;
            }
        }

        $this->lines[] = [
            'product_id' => null,
            'quantity' => '1',
            'unit_cost' => '',
            'vat_rate' => '15',
        ];
        $index = count($this->lines) - 1;
        $this->fillPurchaseLineFromProduct($index, $product);
        $this->addLineProductSearch = '';
        $this->dispatch('document-line-added', lineIndex: $index);
    }

    protected function fillPurchaseLineFromProduct(int $index, Product $product): void
    {
        $this->lines[$index]['product_id'] = $product->id;
        $this->lines[$index]['quantity'] = $this->lines[$index]['quantity'] ?: '1';
        if ($this->lines[$index]['unit_cost'] === '' && $product->cost_ex_vat !== null) {
            $this->lines[$index]['unit_cost'] = (string) round((float) $product->cost_ex_vat, 2);
        }
        $this->lines[$index]['vat_rate'] = DocumentCreateUiSupport::normalizeLineVatRate(null, $product->vat_rate);
    }

    public function openQuickProductModal(): void
    {
        if (! $this->canCreateProductsFromPurchase()) {
            return;
        }

        $this->quickProductVat = '15';
        $this->quickProductItemType = ProductItemType::cases()[0]->value;
        $this->quickProductTrackStock = true;
        $this->quickProductCategoryId = QuickProductCategoryOptions::defaultId();
        $this->quickProductActive = true;
        $this->quickProductFormTab = 'basic';
        $this->resetQuickProductImageUpload();
        $this->showQuickProductModal = true;
    }

    public function saveQuickProduct(): void
    {
        if (! $this->canCreateProductsFromPurchase()) {
            abort(403);
        }

        $this->resetErrorBag();

        $rules = $this->quickProductValidationRuleSet();

        try {
            $this->validate($rules, $this->quickProductImageValidationMessages());
        } catch (ValidationException $e) {
            $this->focusQuickProductTabForErrors(array_keys($e->validator->errors()->messages()));
            $this->toastError(__('catalog.modal_error_summary'), persistent: true);

            throw $e;
        }

        $trackStock = ProductStockSchema::ready() ? $this->quickProductTrackStock : true;

        $costExVat = ProductStockSchema::ready()
            ? ProductCostInput::parseOptional($this->quickProductCost)
            : null;

        $attrs = [
            'name_en' => trim($this->quickProductNameEn),
            'name_ar' => trim($this->quickProductNameAr),
            'price_ex_vat' => round((float) $this->quickProductPrice, 2),
            'cost_ex_vat' => $costExVat,
            'vat_rate' => round((float) $this->quickProductVat, 2),
            'product_category_id' => $this->quickProductCategoryId,
            'item_type' => $this->quickProductItemType,
            'is_active' => $this->quickProductActive,
            'show_on_menu' => true,
            'track_stock' => $trackStock,
        ];

        if (CatalogUnitsSizesSchema::ready()) {
            $attrs['sku'] = ProductSkuGenerator::next();
            $attrs['vat_zatca_category'] = 'S';
            $attrs['product_unit_id'] = ProductUnit::query()->where('code', 'PCS')->value('id');
        }

        if (CatalogI18n::columnsExist() && filled(trim($this->quickProductNameBn))) {
            $attrs['name_bn'] = trim($this->quickProductNameBn);
        }

        $business = $this->resolveQuickProductBusiness();
        $imageUrl = $this->persistQuickProductImage($business);
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }
        if ($imageUrl !== null) {
            $attrs['image_url'] = $imageUrl;
        }

        $product = Product::query()->create($attrs);

        $this->showQuickProductModal = false;
        $this->resetQuickProductForm();
        $this->addPurchaseLineFromProduct($product->id);
        $this->toastSuccess(__('invoices.product_created_applied'));
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function quickProductValidationRuleSet(): array
    {
        $rules = [
            'quickProductNameEn' => ['required', 'string', 'max:160'],
            'quickProductNameAr' => ['required', 'string', 'max:160'],
            'quickProductPrice' => ['required', 'numeric', 'min:0'],
            'quickProductVat' => ['required', 'numeric', 'min:0', 'max:100'],
            'quickProductItemType' => ['required', Rule::in(array_map(fn (ProductItemType $t) => $t->value, ProductItemType::cases()))],
            'quickProductCategoryId' => ['nullable', 'integer', 'exists:product_categories,id'],
            'quickProductActive' => ['boolean'],
        ];

        if (CatalogI18n::columnsExist()) {
            $rules['quickProductNameBn'] = ['nullable', 'string', 'max:160'];
        }

        if (ProductStockSchema::ready()) {
            $rules['quickProductTrackStock'] = ['boolean'];
            $rules['quickProductCost'] = ['nullable', 'numeric', 'min:0'];
        }

        return array_merge($rules, $this->quickProductImageValidationRules());
    }

    protected function resetQuickProductForm(): void
    {
        $this->quickProductNameEn = '';
        $this->quickProductNameAr = '';
        $this->quickProductNameBn = '';
        $this->quickProductPrice = '';
        $this->quickProductVat = '15';
        $this->quickProductItemType = ProductItemType::cases()[0]->value;
        $this->quickProductCategoryId = null;
        $this->quickProductTrackStock = true;
        $this->quickProductActive = true;
        $this->quickProductFormTab = 'basic';
        $this->resetQuickProductImageUpload();
        $this->quickProductCost = '';
        $this->resetValidation();
    }

    protected function resolveQuickProductBusiness(): BusinessSetting
    {
        if (property_exists($this, 'business') && $this->business instanceof BusinessSetting) {
            return $this->business;
        }

        return BusinessSetting::singleton();
    }
}