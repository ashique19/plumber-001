<?php

namespace App\Livewire\Expenses;

use App\Business\BusinessContext;
use App\Enums\BusinessExpenseCategory;
use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\ResolvesBusinessContext;
use App\Models\Branch;
use App\Models\BusinessExpense;
use App\Support\StaffBranchContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.pos')]
class ExpensesHub extends Component
{
    use DispatchesToasts;
    use ResolvesBusinessContext;
    use WithPagination;

    #[Url(as: 'from')]
    public string $dateFrom = '';

    #[Url(as: 'to')]
    public string $dateTo = '';

    #[Url]
    public string $category = '';

    #[Url(as: 'branch')]
    public ?int $branchId = null;

    public bool $showForm = false;

    public string $spentOn = '';

    public string $formCategory = 'other';

    public string $vendor = '';

    public string $description = '';

    public string $amountExVat = '';

    public function mount(): void
    {
        $this->resolveBusiness();
        BusinessContext::set($this->business);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->business->id);
        $this->authorize('reports.view');

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
        $this->showForm = true;
    }

    public function saveExpense(): void
    {
        $this->authorize('staff.manage');

        $validated = $this->validate([
            'spentOn' => ['required', 'date'],
            'formCategory' => ['required', 'in:'.implode(',', BusinessExpenseCategory::values())],
            'vendor' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amountExVat' => ['required', 'numeric', 'min:0.01'],
        ]);

        BusinessExpense::query()->create([
            'branch_id' => $this->branchId,
            'spent_on' => $validated['spentOn'],
            'category' => $validated['formCategory'],
            'vendor' => trim($this->vendor) !== '' ? trim($this->vendor) : null,
            'description' => trim($this->description) !== '' ? trim($this->description) : null,
            'amount_ex_vat' => $validated['amountExVat'],
            'recorded_by_staff_user_id' => auth('staff')->id(),
        ]);

        $this->resetForm();
        $this->showForm = false;
        $this->toastSuccess(__('expenses.saved'));
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

        return view('livewire.expenses.expenses-hub', [
            'expenses' => $this->expensePage(),
            'categories' => BusinessExpenseCategory::cases(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'showBranchSelector' => StaffBranchContext::selectorVisible(),
            'totalExVat' => $this->filteredTotal(),
            'canManage' => $canManage,
        ]);
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
            ->paginate(20);
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
        $this->formCategory = BusinessExpenseCategory::Other->value;
        $this->vendor = '';
        $this->description = '';
        $this->amountExVat = '';
        $this->resetValidation();
    }
}