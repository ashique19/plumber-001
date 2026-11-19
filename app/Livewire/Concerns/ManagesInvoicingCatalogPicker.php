<?php

namespace App\Livewire\Concerns;

use App\Enums\ProductItemType;
use App\Models\Product;
use App\Models\BusinessSetting;
use App\Models\ProductUnit;
use App\Support\Catalog\CatalogI18n;
use App\Support\Catalog\QuickProductCategoryOptions;
use App\Support\Catalog\CatalogUnitsSizesSchema;
use App\Support\Catalog\ProductCostInput;
use App\Support\Catalog\ProductSkuGenerator;
use App\Support\Invoicing\DocumentCatalogLineMerge;
use App\Support\Invoicing\DocumentCreateUiSupport;
use App\Support\Invoicing\InvoicingCatalogLookup;
use App\Services\Inventory\ProductStockService;
use App\Support\Inventory\ProductStockSchema;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait ManagesInvoicingCatalogPicker
{
    use ManagesQuickProductFormTabs;
    use ManagesQuickProductImageUpload;
    /** @var array<int, string> */
    public array $catalogLineSearch = [];

    public string $addLineProductSearch = '';

    public bool $showQuickProductModal = false;

    public ?int $quickProductLineIndex = null;

    public string $quickProductNameEn = '';

    public string $quickProductNameAr = '';

    public string $quickProductPrice = '';

    public string $quickProductVat = '15';

    public string $quickProductItemType = 'item';

    public ?int $quickProductCategoryId = null;

    public bool $quickProductTrackStock = false;

    public string $quickProductCost = '';

    public string $quickProductSku = '';

    public string $quickProductDescriptionEn = '';

    public string $quickProductDescriptionAr = '';

    public string $quickProductDescriptionBn = '';

    public string $quickProductOpeningStock = '';

    public string $quickProductLowStockThreshold = '';

    /**
     * @return list<array{id: int, label: string, meta: string, search: string}>
     */
    public function catalogProductsForLine(int $lineIndex): array
    {
        return InvoicingCatalogLookup::pickerRows($this->catalogLineSearch[$lineIndex] ?? '');
    }

  /**
   * @return Collection<int, Product>
   */
    public function addLineProductSearchResults(): Collection
    {
        return InvoicingCatalogLookup::productsForAddLine($this->addLineProductSearch);
    }

    public function updatedAddLineProductSearch(string $value): void
    {
        if (trim($value) !== '' && property_exists($this, 'customerSearch')) {
            $this->customerSearch = '';
        }
    }

    public function canCreateProductsFromDocument(): bool
    {
        $user = auth('staff')->user();

        return $user !== null
            && $user->can('inventory.view')
            && $user->can('staff.manage');
    }

    public function applyCatalogProduct(int $lineIndex, int $productId): void
    {
        $this->addLineFromProduct($productId);
    }

    public function addLineFromProduct(int $productId, bool $notify = true): void
    {
        $product = Product::query()->visibleInCatalog()->with('unit')->find($productId);
        if ($product === null) {
            return;
        }

        $existingIndex = DocumentCatalogLineMerge::findInvoicingLineIndex($this->lines, $product);
        if ($existingIndex !== null) {
            $bump = DocumentCatalogLineMerge::bumpLineQuantity($this->lines, $existingIndex, 1.0);
            $this->refreshLineStockSnapshot($existingIndex, $product);
            $this->addLineProductSearch = '';
            $lineId = (string) ($this->lines[$existingIndex]['line_id'] ?? '');
            $this->dispatch('document-line-merged', lineIndex: $existingIndex, lineId: $lineId);
            if ($notify) {
                if ($bump['capped']) {
                    $this->toastInfo(__('invoices.catalog_product_qty_capped', ['name' => $product->displayName()]));
                } else {
                    $this->toastInfo(__('invoices.catalog_product_qty_bumped', [
                        'name' => $product->displayName(),
                        'qty' => $bump['quantity'],
                    ]));
                }
            }
            $this->afterCatalogLineChanged();

            return;
        }

        $this->lines[] = $this->emptyLine();
        $index = count($this->lines) - 1;
        $this->fillLineFromProduct($index, $product);
        $this->addLineProductSearch = '';
        $lineId = (string) ($this->lines[$index]['line_id'] ?? '');
        $this->dispatch('document-line-added', lineId: $lineId, lineIndex: $index);
        if ($notify) {
            $this->toastInfo(__('invoices.catalog_product_applied', ['name' => $product->displayName()]));
        }
        $this->afterCatalogLineChanged();
    }

    public function openQuickProductModal(?int $lineIndex = null): void
    {
        if (! $this->canCreateProductsFromDocument()) {
            return;
        }

        $this->quickProductLineIndex = $lineIndex;
        $this->quickProductVat = '15';
        $this->quickProductItemType = ProductItemType::cases()[0]->value;
        $this->quickProductCategoryId = QuickProductCategoryOptions::defaultId();
        $this->quickProductActive = true;
        $this->quickProductFormTab = 'basic';
        $this->resetQuickProductImageUpload();
        $this->showQuickProductModal = true;
    }

    public function saveQuickProduct(ProductStockService $stock): void
    {
        if (! $this->canCreateProductsFromDocument()) {
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
            'track_stock' => ProductStockSchema::ready() ? $this->quickProductTrackStock : false,
        ];

        if (CatalogUnitsSizesSchema::ready()) {
            $attrs['sku'] = ProductSkuGenerator::next();
            $attrs['vat_zatca_category'] = 'S';
            $attrs['product_unit_id'] = ProductUnit::query()->where('code', 'PCS')->value('id');
        }

        if (CatalogI18n::columnsExist()) {
            if (filled(trim($this->quickProductNameBn))) {
                $attrs['name_bn'] = trim($this->quickProductNameBn);
            }
            $attrs['description_en'] = trim($this->quickProductDescriptionEn) ?: null;
            $attrs['description_ar'] = trim($this->quickProductDescriptionAr) ?: null;
            $attrs['description_bn'] = trim($this->quickProductDescriptionBn) ?: null;
        }

        if (ProductStockSchema::ready()) {
            $attrs['low_stock_threshold'] = round((float) $this->quickProductLowStockThreshold, 3);
        }

        if (! CatalogUnitsSizesSchema::ready() && filled(trim($this->quickProductSku))) {
            $attrs['sku'] = trim($this->quickProductSku);
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

        if (ProductStockSchema::ready() && $product->track_stock) {
            $opening = (float) $this->quickProductOpeningStock;
            $stock->recordOpeningStock(
                $product,
                $opening,
                $costExVat ?? 0.0,
                null,
                auth('staff')->id(),
            );
        }

        $this->showQuickProductModal = false;
        $this->quickProductLineIndex = null;
        $this->resetQuickProductForm();
        $this->addLineFromProduct($product->id, notify: false);
        $this->toastSuccess(__('invoices.product_created_applied'));
    }

    protected function fillLineFromProduct(int $lineIndex, Product $product): void
    {
        $nameEn = trim((string) ($product->name_en ?? ''));
        if ($nameEn === '') {
            $nameEn = $product->displayName('en');
        }

        $nameAr = trim((string) ($product->name_ar ?? ''));
        if ($nameAr === '') {
            $nameAr = $nameEn;
        }

        $nameBn = trim((string) ($product->name_bn ?? ''));

        $this->lines[$lineIndex] = array_merge($this->lines[$lineIndex], [
            'name' => $nameEn,
            'name_ar' => $nameAr,
            'name_bn' => $nameBn,
            'quantity' => $this->lines[$lineIndex]['quantity'] !== '' ? $this->lines[$lineIndex]['quantity'] : '1',
            'unit_price' => (string) $product->price_ex_vat,
            'vat_rate' => DocumentCreateUiSupport::normalizeLineVatRate(null, $product->vat_rate),
            'discount_percent' => (string) ($this->lines[$lineIndex]['discount_percent'] ?? '0'),
            'product_id' => $product->id,
            'product_unit_id' => $product->product_unit_id,
            'product_size_id' => $product->product_size_id,
            'unit_label' => $product->unitPickerLabel(),
            'unit_code' => $product->ublUnitCode(),
            'vat_zatca_category' => $product->vatZatcaCategory()->value,
            'product_image_url' => filled($product->image_url) ? (string) $product->image_url : '',
            'track_stock' => (bool) $product->track_stock,
            'stock_quantity' => $product->track_stock ? (string) $product->stock_quantity : '',
        ]);
    }

    protected function refreshLineStockSnapshot(int $lineIndex, Product $product): void
    {
        if (! isset($this->lines[$lineIndex])) {
            return;
        }

        $this->lines[$lineIndex]['track_stock'] = (bool) $product->track_stock;
        $this->lines[$lineIndex]['stock_quantity'] = $product->track_stock
            ? (string) $product->stock_quantity
            : '';
    }

    protected function afterCatalogLineChanged(): void
    {
        if (method_exists($this, 'syncAmountReceivedToGrandTotal')) {
            $this->syncAmountReceivedToGrandTotal();
        }
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
            $rules['quickProductOpeningStock'] = [
                Rule::requiredIf(fn (): bool => $this->quickProductTrackStock),
                'nullable',
                'numeric',
                'min:0',
            ];
            $rules['quickProductLowStockThreshold'] = ['nullable', 'numeric', 'min:0'];
        }

        if (! CatalogUnitsSizesSchema::ready()) {
            $rules['quickProductSku'] = ['nullable', 'string', 'max:64'];
        }

        if (CatalogI18n::columnsExist()) {
            $rules['quickProductDescriptionEn'] = ['nullable', 'string', 'max:2000'];
            $rules['quickProductDescriptionAr'] = ['nullable', 'string', 'max:2000'];
            $rules['quickProductDescriptionBn'] = ['nullable', 'string', 'max:2000'];
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
        $this->quickProductTrackStock = false;
        $this->quickProductActive = true;
        $this->quickProductFormTab = 'basic';
        $this->resetQuickProductImageUpload();
        $this->quickProductCost = '';
        $this->quickProductSku = '';
        $this->quickProductDescriptionEn = '';
        $this->quickProductDescriptionAr = '';
        $this->quickProductDescriptionBn = '';
        $this->quickProductOpeningStock = '';
        $this->quickProductLowStockThreshold = '';
        $this->resetValidation();
    }

    protected function resolveQuickProductBusiness(): BusinessSetting
    {
        if (property_exists($this, 'business') && $this->business instanceof BusinessSetting) {
            return $this->business;
        }

        return BusinessSetting::singleton();
    }

    /**
     * @return list<array{
     *     name: string,
     *     name_ar: string,
     *     quantity: string,
     *     unit_price: string,
     *     vat_rate: string,
     *     product_id: int|null,
     *     unit_label: string
     * }>
     */
    abstract protected function emptyLine(): array;
}