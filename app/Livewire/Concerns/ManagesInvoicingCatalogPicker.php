<?php

namespace App\Livewire\Concerns;

use App\Enums\ProductItemType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Support\Catalog\CatalogUnitsSizesSchema;
use App\Support\Catalog\ProductSkuGenerator;
use App\Support\Invoicing\InvoicingCatalogLookup;
use App\Support\Inventory\ProductStockSchema;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

trait ManagesInvoicingCatalogPicker
{
    /** @var array<int, string> */
    public array $catalogLineSearch = [];

    public string $addLineProductSearch = '';

    public bool $showQuickProductModal = false;

    public ?int $quickProductLineIndex = null;

    public string $quickProductNameEn = '';

    public string $quickProductNameAr = '';

    public string $quickProductPrice = '';

    public string $quickProductVat = '15';

    public string $quickProductItemType = 'service';

    public ?int $quickProductCategoryId = null;

    public bool $quickProductTrackStock = false;

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

    public function addLineFromProduct(int $productId): void
    {
        $product = Product::query()->visibleInCatalog()->with('unit')->find($productId);
        if ($product === null) {
            return;
        }

        $this->lines[] = $this->emptyLine();
        $index = count($this->lines) - 1;
        $this->fillLineFromProduct($index, $product);
        $this->addLineProductSearch = '';
        $this->dispatch('document-line-added');
        $this->toastInfo(__('invoices.catalog_product_applied', ['name' => $product->displayName()]));
    }

    public function openQuickProductModal(?int $lineIndex = null): void
    {
        if (! $this->canCreateProductsFromDocument()) {
            return;
        }

        $this->quickProductLineIndex = $lineIndex;
        $this->quickProductVat = '15';
        $this->quickProductItemType = ProductItemType::Service->value;
        $this->showQuickProductModal = true;
    }

    public function saveQuickProduct(): void
    {
        if (! $this->canCreateProductsFromDocument()) {
            abort(403);
        }

        $rules = [
            'quickProductNameEn' => ['required', 'string', 'max:160'],
            'quickProductNameAr' => ['required', 'string', 'max:160'],
            'quickProductPrice' => ['required', 'numeric', 'min:0'],
            'quickProductVat' => ['required', 'numeric', 'min:0', 'max:100'],
            'quickProductItemType' => ['required', Rule::in(array_map(fn (ProductItemType $t) => $t->value, ProductItemType::cases()))],
            'quickProductCategoryId' => ['nullable', 'integer', 'exists:product_categories,id'],
        ];

        if (ProductStockSchema::ready()) {
            $rules['quickProductTrackStock'] = ['boolean'];
        }

        $this->validate($rules);

        $attrs = [
            'name_en' => trim($this->quickProductNameEn),
            'name_ar' => trim($this->quickProductNameAr),
            'price_ex_vat' => round((float) $this->quickProductPrice, 2),
            'cost_ex_vat' => round((float) $this->quickProductPrice, 2),
            'vat_rate' => round((float) $this->quickProductVat, 2),
            'product_category_id' => $this->quickProductCategoryId,
            'item_type' => $this->quickProductItemType,
            'is_active' => true,
            'show_on_menu' => true,
            'track_stock' => ProductStockSchema::ready() ? $this->quickProductTrackStock : false,
        ];

        if (CatalogUnitsSizesSchema::ready()) {
            $attrs['sku'] = ProductSkuGenerator::next();
            $attrs['vat_zatca_category'] = 'S';
            $attrs['product_unit_id'] = ProductUnit::query()->where('code', 'PCS')->value('id');
        }

        $product = Product::query()->create($attrs);

        $this->lines[] = $this->emptyLine();
        $lineIndex = count($this->lines) - 1;
        $this->fillLineFromProduct($lineIndex, $product);
        $this->showQuickProductModal = false;
        $this->quickProductLineIndex = null;
        $this->resetQuickProductForm();
        $this->dispatch('document-line-added');
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
            'vat_rate' => (string) $product->vat_rate,
            'discount_percent' => (string) ($this->lines[$lineIndex]['discount_percent'] ?? '0'),
            'product_id' => $product->id,
            'unit_label' => $product->unitPickerLabel(),
            'unit_code' => $product->ublUnitCode(),
            'vat_zatca_category' => $product->vatZatcaCategory()->value,
            'product_image_url' => filled($product->image_url) ? (string) $product->image_url : '',
            'track_stock' => (bool) $product->track_stock,
            'stock_quantity' => $product->track_stock ? (string) $product->stock_quantity : '',
        ]);
    }

    protected function resetQuickProductForm(): void
    {
        $this->quickProductNameEn = '';
        $this->quickProductNameAr = '';
        $this->quickProductPrice = '';
        $this->quickProductVat = '15';
        $this->quickProductItemType = ProductItemType::Service->value;
        $this->quickProductCategoryId = null;
        $this->quickProductTrackStock = false;
        $this->resetValidation();
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