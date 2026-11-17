<?php

namespace App\Livewire\Expenses;

use App\Business\BusinessContext;
use App\Enums\BusinessExpenseCategory;
use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\HasAdminHubPagination;
use App\Livewire\Concerns\ResolvesBusinessContext;
use App\Livewire\Concerns\ValidatesFieldsLive;
use App\Models\Branch;
use App\Models\BusinessExpense;
use App\Models\ExpenseCategory;
use App\Support\Expenses\ExpenseCategoryCatalog;
use App\Support\Hub\HubFormSubmissionIdempotency;
use App\Support\StaffBranchContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.pos')]
class ExpensesHub extends Component
{
    use DispatchesToasts;
    use HasAdminHubPagination;
    use ResolvesBusinessContext;
    use ValidatesFieldsLive;

    #[Url(as: 'from')]
    public string $dateFrom = '';

    #[Url(as: 'to')]
    public string $dateTo = '';

    #[Url]
    public string $category = '';

    #[Url(as: 'branch')]
    public ?int $branchId = null;

    public bool $showForm = false;

    public bool $showCategoriesModal = false;

    public string $spentOn = '';

    public string $formCategory = 'other';

    public string $vendor = '';

    public string $description = '';

    public string $amountExVat = '';

    public string $newCategoryName = '';

    public ?int $editingCategoryId = null;

    public string $editingCategoryName = '';

    public ?int $pendingDeleteCategoryId = null;

    public string $mergeCategorySlug = '';

    #[Locked]
    public string $expenseSubmissionToken = '';

    public function mount(): void
    {
        $this->resolveBusiness();
        BusinessContext::set($this->business);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->business->id);
        $this->authorize('reports.view');

        ExpenseCategoryCatalog::ensureDefaults();
        $this->normalizeCategorySelections();

        $tz = config('app.timezone');
        if ($this->dateFrom === '' || $this->dateTo === '') {
            $this->dateTo = now($tz)->toDateString();
            $this->dateFrom = now($tz)->subDays(29)->toDateString();
        }

        if ($this->branchId === null && ! StaffBranchContext::selectorVisible()) {
            $default = StaffBranchContext::resolve();
            $this->branchId = $default?->id;
        }

        if ($this->spentOn === '') {
            $this->spentOn = now($tz)->toDateString();
        }
    }

    public function updatingCategory(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function updatingBranchId(): void
    {
        $this->resetPage();
    }

    public function openForm(): void
    {
        $this->authorize('staff.manage');
        $this->expenseSubmissionToken = (string) Str::uuid();
        $this->showForm = true;
    }

    public function closeExpenseForm(): void
    {
        $this->showForm = false;
    }

    public function openCategoriesModal(): void
    {
        $this->authorize('staff.manage');
        $this->resetCategoryEditorState();
        $this->showCategoriesModal = true;
    }

    public function closeCategoriesModal(): void
    {
        $this->showCategoriesModal = false;
        $this->resetCategoryEditorState();
        $this->normalizeCategorySelections();
    }

    public function addCategory(): void
    {
        $this->authorize('staff.manage');

        $name = trim($this->newCategoryName);
        $this->validate([
            'newCategoryName' => ['required', 'string', 'min:1', 'max:120'],
        ], [], [
            'newCategoryName' => __('expenses.categories_modal.name'),
        ]);

        ExpenseCategory::query()->create([
            'slug' => ExpenseCategoryCatalog::uniqueSlugFromName($name),
            'name' => $name,
        ]);

        $this->newCategoryName = '';
        $this->normalizeCategorySelections();
        $this->toastSuccess(__('expenses.categories_modal.created'));
    }

    public function startEditCategory(int $categoryId): void
    {
        $this->authorize('staff.manage');
        $cat = ExpenseCategory::query()->findOrFail($categoryId);
        $this->editingCategoryId = $cat->id;
        $this->editingCategoryName = $cat->name;
        $this->pendingDeleteCategoryId = null;
        $this->mergeCategorySlug = '';
    }

    public function cancelEditCategory(): void
    {
        $this->editingCategoryId = null;
        $this->editingCategoryName = '';
    }

    public function saveCategoryRename(): void
    {
        $this->authorize('staff.manage');

        $this->validate([
            'editingCategoryName' => ['required', 'string', 'min:1', 'max:120'],
        ], [], [
            'editingCategoryName' => __('expenses.categories_modal.name'),
        ]);

        $cat = ExpenseCategory::query()->findOrFail($this->editingCategoryId);
        $cat->update(['name' => trim($this->editingCategoryName)]);
        $this->cancelEditCategory();
        $this->toastSuccess(__('expenses.categories_modal.renamed'));
    }

    public function promptDeleteCategory(int $categoryId): void
    {
        $this->authorize('staff.manage');
        $this->cancelEditCategory();
        $this->pendingDeleteCategoryId = $categoryId;
        $this->mergeCategorySlug = '';
    }

    public function cancelDeleteCategory(): void
    {
        $this->pendingDeleteCategoryId = null;
        $this->mergeCategorySlug = '';
    }

    public function deleteCategory(): void
    {
        $this->authorize('staff.manage');

        $cat = ExpenseCategory::query()->findOrFail($this->pendingDeleteCategoryId);
        $usage = $cat->expenses()->count();

        if ($usage > 0) {
            $otherSlugs = ExpenseCategory::query()
                ->whereKeyNot($cat->id)
                ->pluck('slug')
                ->all();

            $this->validate([
                'mergeCategorySlug' => ['required', Rule::in($otherSlugs)],
            ], [
                'mergeCategorySlug.required' => __('expenses.categories_modal.merge_required', ['count' => $usage]),
            ], [
                'mergeCategorySlug' => __('expenses.categories_modal.merge_into'),
            ]);

            BusinessExpense::query()
                ->where('category', $cat->slug)
                ->update(['category' => $this->mergeCategorySlug]);
        }

        if ($this->category === $cat->slug) {
            $this->category = $usage > 0 ? $this->mergeCategorySlug : '';
        }
        if ($this->formCategory === $cat->slug) {
            $this->formCategory = $usage > 0 ? $this->mergeCategorySlug : (ExpenseCategory::query()->value('slug') ?? BusinessExpenseCategory::Other->value);
        }

        $cat->delete();
        $this->cancelDeleteCategory();
        $this->normalizeCategorySelections();
        $this->toastSuccess(__('expenses.categories_modal.deleted'));
    }

    public function saveExpense(): void
    {
        $this->authorize('staff.manage');

        $validated = $this->validate([
            'spentOn' => ['required', 'date'],
            'formCategory' => ['required', Rule::in(ExpenseCategoryCatalog::allowedSlugs())],
            'vendor' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amountExVat' => ['required', 'numeric', 'min:0.01'],
        ]);

        $actorId = auth('staff')->id();
        $scope = 'expense_save';

        if (HubFormSubmissionIdempotency::storedResourceId($scope, $this->expenseSubmissionToken, $actorId) !== null) {
            $this->resetForm();
            $this->showForm = false;
            $this->toastSuccess(__('expenses.saved'));

            return;
        }

        $lock = HubFormSubmissionIdempotency::acquireLock($scope, $this->expenseSubmissionToken, $actorId);
        if ($lock === null) {
            if (HubFormSubmissionIdempotency::waitForStoredResourceId($scope, $this->expenseSubmissionToken, $actorId) !== null) {
                $this->resetForm();
                $this->showForm = false;
                $this->toastSuccess(__('expenses.saved'));
            }

            return;
        }

        try {
            if (HubFormSubmissionIdempotency::storedResourceId($scope, $this->expenseSubmissionToken, $actorId) !== null) {
                $this->resetForm();
                $this->showForm = false;
                $this->toastSuccess(__('expenses.saved'));

                return;
            }

            $expense = BusinessExpense::query()->create([
                'branch_id' => $this->branchId,
                'spent_on' => $validated['spentOn'],
                'category' => $validated['formCategory'],
                'vendor' => trim($this->vendor) !== '' ? trim($this->vendor) : null,
                'description' => trim($this->description) !== '' ? trim($this->description) : null,
                'amount_ex_vat' => $validated['amountExVat'],
                'recorded_by_staff_user_id' => $actorId,
            ]);

            HubFormSubmissionIdempotency::rememberResourceId($scope, $this->expenseSubmissionToken, $actorId, (int) $expense->id);

            $this->resetForm();
            $this->showForm = false;
            $this->toastSuccess(__('expenses.saved'));
        } finally {
            $lock->release();
        }
    }

    public function deleteExpense(int $expenseId): void
    {
        $this->authorize('staff.manage');
        BusinessExpense::query()->whereKey($expenseId)->delete();
        $this->toastSuccess(__('expenses.deleted'));
    }

    public function render(): View
    {
        $canManage = auth('staff')->user()?->can('staff.manage') ?? false;

        $categories = $this->categoryOptions();
        $expenseCategories = ExpenseCategory::query()
            ->withCount('expenses')
            ->orderByDesc('id')
            ->get();

        return view('livewire.expenses.expenses-hub', [
            'expenses' => $this->expensePage(),
            'categories' => $categories,
            'expenseCategories' => $expenseCategories,
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'showBranchSelector' => StaffBranchContext::selectorVisible(),
            'totalExVat' => $this->filteredTotal(),
            'canManage' => $canManage,
        ]);
    }

    /**
     * @return Collection<int, ExpenseCategory>
     */
    private function categoryOptions(): Collection
    {
        if (! ExpenseCategoryCatalog::ready()) {
            return collect();
        }

        return ExpenseCategory::query()->orderBy('name')->get();
    }

    private function normalizeCategorySelections(): void
    {
        $allowed = ExpenseCategoryCatalog::allowedSlugs();

        if ($this->category !== '' && ! in_array($this->category, $allowed, true)) {
            $this->category = '';
        }

        if ($this->formCategory === '' || ! in_array($this->formCategory, $allowed, true)) {
            $this->formCategory = in_array('other', $allowed, true)
                ? 'other'
                : ($allowed[0] ?? BusinessExpenseCategory::Other->value);
        }
    }

    private function filteredTotal(): float
    {
        return round((float) $this->filteredQuery()->sum('amount_ex_vat'), 2);
    }

    private function expensePage(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->with(['branch', 'recordedBy'])
            ->orderByDesc('spent_on')
            ->orderByDesc('id')
            ->paginate($this->adminPerPage());
    }

    private function filteredQuery()
    {
        $query = BusinessExpense::query();

        if ($this->dateFrom !== '') {
            $query->whereDate('spent_on', '>=', $this->dateFrom);
        }
        if ($this->dateTo !== '') {
            $query->whereDate('spent_on', '<=', $this->dateTo);
        }
        if ($this->category !== '') {
            $query->where('category', $this->category);
        }
        if ($this->branchId !== null) {
            $query->where(function ($q): void {
                $q->whereNull('branch_id')->orWhere('branch_id', $this->branchId);
            });
        }

        return $query;
    }

    private function resetForm(): void
    {
        $this->spentOn = now(config('app.timezone'))->toDateString();
        $this->formCategory = in_array('other', ExpenseCategoryCatalog::allowedSlugs(), true)
            ? 'other'
            : (ExpenseCategoryCatalog::allowedSlugs()[0] ?? BusinessExpenseCategory::Other->value);
        $this->vendor = '';
        $this->description = '';
        $this->amountExVat = '';
        $this->resetValidation();
    }

    private function resetCategoryEditorState(): void
    {
        $this->newCategoryName = '';
        $this->cancelEditCategory();
        $this->cancelDeleteCategory();
        $this->resetErrorBag(['newCategoryName', 'editingCategoryName', 'mergeCategorySlug']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function liveValidationRules(): array
    {
        return [
            'spentOn' => ['required', 'date'],
            'formCategory' => ['required', Rule::in(ExpenseCategoryCatalog::allowedSlugs())],
            'vendor' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amountExVat' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}