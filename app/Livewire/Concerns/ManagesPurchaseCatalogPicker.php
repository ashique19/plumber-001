<?php

namespace App\Livewire\Concerns;

use App\Enums\ProductItemType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\Invoicing\InvoicingCatalogLookup;
use App\Support\Inventory\ProductStockSchema;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

trait ManagesPurchaseCatalogPicker
{
    public string $addLineProductSearch = '';

    public bool $showQuickProductModal = false;

    public string $quickProductNameEn = '';

    public string $quickProductNameAr = '';

    public string $quickProductPrice = '';

    public string $quickProductVat = '15';

    public string $quickProductItemType = 'item';

    public ?int $quickProductCategoryId = null;

    public bool $quickProductTrackStock = true;

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

        foreach ($this->lines as $index => $line) {
            if (! filled($line['product_id'] ?? null)) {
                $this->fillPurchaseLineFromProduct($index, $product);

                $this->addLineProductSearch = '';

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
    }

    protected function fillPurchaseLineFromProduct(int $index, Product $product): void
    {
        $this->lines[$index]['product_id'] = $product->id;
        $this->lines[$index]['quantity'] = $this->lines[$index]['quantity'] ?: '1';
        if ($this->lines[$index]['unit_cost'] === '') {
            $this->lines[$index]['unit_cost'] = (string) round((float) $product->cost_ex_vat, 2);
        }
        $this->lines[$index]['vat_rate'] = (string) $product->vat_rate;
    }

    public function openQuickProductModal(): void
    {
        if (! $this->canCreateProductsFromPurchase()) {
            return;
        }

        $this->quickProductVat = '15';
        $this->quickProductItemType = ProductItemType::Item->value;
        $this->quickProductTrackStock = true;
        $this->showQuickProductModal = true;
    }

    public function saveQuickProduct(): void
    {
        if (! $this->canCreateProductsFromPurchase()) {
            abort(403);
        }

        $rules = [
            'quickProductNameEn' => ['required', 'string', 'max:160'],
            'quickProductNameAr' => ['nullable', 'string', 'max:160'],
            'quickProductPrice' => ['required', 'numeric', 'min:0'],
            'quickProductVat' => ['required', 'numeric', 'min:0', 'max:100'],
            'quickProductItemType' => ['required', Rule::in(array_map(fn (ProductItemType $t) => $t->value, ProductItemType::cases()))],
            'quickProductCategoryId' => ['nullable', 'integer', 'exists:product_categories,id'],
        ];

        if (ProductStockSchema::ready()) {
            $rules['quickProductTrackStock'] = ['boolean'];
        }

        $this->validate($rules);

        $trackStock = ProductStockSchema::ready() ? $this->quickProductTrackStock : true;

        $nameAr = trim($this->quickProductNameAr);
        if ($nameAr === '') {
            $nameAr = trim($this->quickProductNameEn);
        }

        $product = Product::query()->create([
            'name_en' => trim($this->quickProductNameEn),
            'name_ar' => $nameAr,
            'price_ex_vat' => round((float) $this->quickProductPrice, 2),
            'cost_ex_vat' => round((float) $this->quickProductPrice, 2),
            'vat_rate' => round((float) $this->quickProductVat, 2),
            'product_category_id' => $this->quickProductCategoryId,
            'item_type' => $this->quickProductItemType,
            'is_active' => true,
            'show_on_menu' => true,
            'track_stock' => $trackStock,
        ]);

        $this->showQuickProductModal = false;
        $this->resetQuickProductForm();
        $this->addPurchaseLineFromProduct($product->id);
        $this->toastSuccess(__('invoices.product_created_applied'));
    }

    protected function resetQuickProductForm(): void
    {
        $this->quickProductNameEn = '';
        $this->quickProductNameAr = '';
        $this->quickProductPrice = '';
        $this->quickProductVat = '15';
        $this->quickProductItemType = ProductItemType::Item->value;
        $this->quickProductCategoryId = null;
        $this->quickProductTrackStock = true;
        $this->resetValidation();
    }
}