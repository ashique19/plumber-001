<?php

namespace App\Livewire\Catalog;

use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\ValidatesFieldsLive;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Livewire\Concerns\ResolvesBusinessContext;
use App\Services\Inventory\IngredientStockService;
use App\Support\Inventory\IngredientInventorySchema;
use App\Business\BusinessContext;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.pos')]
class InventoryManager extends Component
{
    use AuthorizesRequests;
    use DispatchesToasts;
    use ResolvesBusinessContext;
    use ValidatesFieldsLive;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $nameEn = '';

    public string $nameAr = '';

    public string $nameBn = '';

    public string $unit = 'g';

    public string $quantityOnHand = '0';

    public string $lowStockThreshold = '0';

    public bool $isActive = true;

    public bool $showAdjust = false;

    public ?int $adjustId = null;

    /** @var 'add'|'remove'|'set' */
    public string $adjustMode = 'add';

    public string $adjustAmount = '';

    public bool $schemaReady = true;

    public function mount(): void
    {
        $this->resolveBusiness();
        $this->schemaReady = IngredientInventorySchema::ingredientsReady();
        BusinessContext::set($this->business);
    }

    public function startCreate(): void
    {
        $this->authorizeManage();
        $this->resetForm();
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        $this->authorizeManage();
        $ing = $this->query()->findOrFail($id);
        $this->editingId = $ing->id;
        $this->nameEn = (string) ($ing->name_en ?: $ing->name);
        $this->nameAr = (string) ($ing->name_ar ?? '');
        $this->nameBn = (string) ($ing->name_bn ?? '');
        $this->unit = (string) $ing->unit;
        $this->quantityOnHand = (string) $ing->quantity_on_hand;
        $this->lowStockThreshold = IngredientInventorySchema::hasLowStockThreshold()
            ? (string) ($ing->low_stock_threshold ?? 0)
            : (string) ($ing->reorder_level ?? 0);
        $this->isActive = (bool) ($ing->is_active ?? true);
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManage();

        $validated = $this->validate([
            'nameEn' => ['required', 'string', 'max:160'],
            'nameAr' => ['nullable', 'string', 'max:160'],
            'nameBn' => ['nullable', 'string', 'max:160'],
            'unit' => ['required', 'string', 'max:16'],
            'quantityOnHand' => ['required', 'numeric', 'min:0'],
            'lowStockThreshold' => ['required', 'numeric', 'min:0'],
            'isActive' => ['boolean'],
        ]);

        $payload = [
            'name_en' => $validated['nameEn'],
            'name_ar' => $validated['nameAr'] ?: null,
            'name' => $validated['nameEn'],
            'unit' => $validated['unit'],
            'quantity_on_hand' => $validated['quantityOnHand'],
            'reorder_level' => $validated['lowStockThreshold'],
            'is_active' => $validated['isActive'],
            'branch_id' => null,
        ];

        if (IngredientInventorySchema::hasI18nColumns()) {
            $payload['name_bn'] = $validated['nameBn'] ?: null;
        }
        if (IngredientInventorySchema::hasLowStockThreshold()) {
            $payload['low_stock_threshold'] = $validated['lowStockThreshold'];
        }

        if ($this->editingId) {
            $this->query()->findOrFail($this->editingId)->update($payload);
            $this->toastSuccess(__('inventory.saved'));
        } else {
            $this->query()->create($payload);
            $this->toastSuccess(__('inventory.created'));
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorizeManage();
        $this->query()->findOrFail($id)->delete();
        $this->toastSuccess(__('inventory.deleted'));
    }

    public function startAdjust(int $id): void
    {
        $this->authorizeManage();
        $this->adjustId = $id;
        $this->adjustMode = 'add';
        $this->adjustAmount = '';
        $this->showAdjust = true;
    }

    public function saveAdjust(IngredientStockService $stock): void
    {
        $this->authorizeManage();

        if ($this->adjustId === null) {
            return;
        }

        $validated = $this->validate([
            'adjustMode' => ['required', 'in:add,remove,set'],
            'adjustAmount' => ['required', 'numeric', 'min:0'],
            'adjustId' => ['required', 'integer'],
        ]);

        $ingredient = $this->query()->findOrFail($this->adjustId);
        $user = auth('staff')->user();
        abort_unless($user !== null, 403);

        $branch = $user->branch ?? Branch::query()->first();
        $stock->adjustStock(
            $ingredient,
            (float) $validated['adjustAmount'],
            $validated['adjustMode'],
            $user,
            $branch,
        );

        $this->showAdjust = false;
        $this->adjustId = null;
        $this->toastSuccess(__('inventory.adjusted'));
    }

    public function render(IngredientStockService $stock): View
    {
        $ingredients = $this->schemaReady
            ? $this->query()->orderBy('name_en')->orderBy('name')->get()
            : collect();
        $movements = IngredientInventorySchema::movementsReady()
            ? InventoryMovement::query()
                ->latest('id')
                ->limit(40)
                ->get()
            : collect();

        return view('livewire.catalog.inventory-manager', [
            'ingredients' => $ingredients,
            'movements' => $movements,
            'canManage' => auth('staff')->user()?->can('inventory.manage') ?? false,
            'lowStock' => $this->schemaReady ? $stock->lowStockForBusiness() : [],
            'schemaReady' => $this->schemaReady,
        ])->layout('layouts.pos', ['pageTitle' => __('inventory.title')]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function liveValidationRules(): array
    {
        return [
            'adjustMode' => 'required|in:add,remove,set',
            'adjustAmount' => 'nullable|numeric|min:0',
            'nameEn' => 'required|string|max:160',
            'nameAr' => 'nullable|string|max:160',
            'nameBn' => 'nullable|string|max:160',
            'unit' => 'required|string|max:16',
            'quantityOnHand' => 'nullable|numeric|min:0',
            'lowStockThreshold' => 'nullable|numeric|min:0',
        ];
    }

    private function query()
    {
        return Ingredient::query()->whereNull('branch_id');
    }

    private function authorizeManage(): void
    {
        abort_unless(auth('staff')->user()?->can('inventory.manage'), 403);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->nameEn = '';
        $this->nameAr = '';
        $this->nameBn = '';
        $this->unit = 'g';
        $this->quantityOnHand = '0';
        $this->lowStockThreshold = '0';
        $this->isActive = true;
    }
}
