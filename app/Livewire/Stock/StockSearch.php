<?php

namespace App\Livewire\Stock;

use App\Business\BusinessContext;
use App\Livewire\Concerns\HasAdminHubPagination;
use App\Livewire\Concerns\ResolvesBusinessContext;
use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Support\Inventory\ProductStockSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.pos')]
class StockSearch extends Component
{
    use HasAdminHubPagination;
    use ResolvesBusinessContext;

    #[Url]
    public string $search = '';

    #[Url]
    public bool $lowStockOnly = false;

    #[Url]
    public ?int $product = null;

    public bool $schemaReady = true;

    public function mount(): void
    {
        $this->resolveBusiness();
        BusinessContext::set($this->business);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->business->id);
        abort_unless(auth('staff')->user()?->can('inventory.view'), 403);
        $this->schemaReady = ProductStockSchema::ready();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingLowStockOnly(): void
    {
        $this->resetPage();
    }

    public function selectProduct(int $productId): void
    {
        $this->product = $productId;
    }

    public function clearProduct(): void
    {
        $this->product = null;
    }

    public function render(): View
    {
        $products = $this->schemaReady ? $this->productQuery()->paginate($this->adminPerPage()) : null;

        $movements = $this->schemaReady
            ? $this->movementQuery()->limit(40)->get()
            : collect();

        $selected = $this->product && $this->schemaReady
            ? Product::query()->find($this->product)
            : null;

        return view('livewire.stock.stock-search', [
            'products' => $products,
            'movements' => $movements,
            'selected' => $selected,
            'pageTitle' => __('stock.title'),
        ])->layout('layouts.pos', ['pageTitle' => __('stock.title')]);
    }

    /**
     * @return Builder<Product>
     */
    private function productQuery(): Builder
    {
        $query = Product::query()
            ->where('track_stock', true)
            ->orderBy('name_en');

        if (trim($this->search) !== '') {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($this->search)).'%';
            $query->where(function ($q) use ($term): void {
                $q->where('name_en', 'like', $term)
                    ->orWhere('name_ar', 'like', $term)
                    ->orWhere('sku', 'like', $term);
            });
        }

        if ($this->lowStockOnly) {
            $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
        }

        return $query;
    }

    /**
     * @return Builder<ProductStockMovement>
     */
    private function movementQuery(): Builder
    {
        $query = ProductStockMovement::query()
            ->with(['product', 'invoice', 'purchase'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($this->product !== null) {
            $query->where('product_id', $this->product);
        }

        return $query;
    }
}
