<?php

namespace App\Livewire\Concerns;

use App\Enums\ProductItemType;
use App\Models\Product;
use App\Models\ProductCategory;
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
        $product = Product::query()->where('is_active', true)->find($productId);
        if ($product === null) {
            return;
        }

        $this->lines[] = $this->emptyLine();
        $index = count($this->lines) - 1;
        $this->fillLineFromProduct($index, $product);
        $this->addLineProductSearch = '';
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

        $product = Product::query()->create([
            'name_en' => trim($this->quickProductNameEn),
            'name_ar' => trim($this->quickProductNameAr),
            'price_ex_vat' => round((float) $this->quickProductPrice, 2),
            'vat_rate' => round((float) $this->quickProductVat, 2),
            'product_category_id' => $this->quickProductCategoryId,
            'item_type' => $this->quickProductItemType,
            'is_active' => true,
            'show_on_menu' => true,
            'track_stock' => ProductStockSchema::ready() ? $this->quickProductTrackStock : false,
        ]);

        $this->lines[] = $this->emptyLine();
        $lineIndex = count($this->lines) - 1;
        $this->fillLineFromProduct($lineIndex, $product);
        $this->showQuickProductModal = false;
        $this->quickProductLineIndex = null;
        $this->resetQuickProductForm();
        $this->toastSuccess(__('invoices.product_created_applied'));
    }

    protected function fillLineFromProduct(int $lineIndex, Product $product): void
    {
        $nameEn = trim((string) ($product->name_en ?? ''));
        if ($nameEn === '') {
            $nameEn = $product->displayName('en');
        }

        $this->lines[$lineIndex] = array_merge($this->lines[$lineIndex], [
            'name' => $nameEn,
            'name_ar' => trim((string) ($product->name_ar ?? '')),
            'quantity' => $this->lines[$lineIndex]['quantity'] !== '' ? $this->lines[$lineIndex]['quantity'] : '1',
            'unit_price' => (string) $product->price_ex_vat,
            'vat_rate' => (string) $product->vat_rate,
            'product_id' => $product->id,
            'unit_label' => $product->itemType()->label(),
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