<div class="expenses-hub pb-24 lg:pb-8">
    <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="app-page-title">{{ __('expenses.title') }}</h1>
            <p class="app-page-lead">{{ __('expenses.subtitle') }}</p>
        </div>
        @if ($canManage)
            <button type="button" wire:click="openForm" class="tap-action min-h-11 px-4 text-sm">
                {{ __('expenses.add') }}
            </button>
        @endif
    </header>

    <section class="app-card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
        <label class="form-field min-w-[9rem] flex-1">
            <span class="form-field__label">{{ __('expenses.from') }}</span>
            <input type="date" wire:model.live="dateFrom" class="form-field__control">
        </label>
        <label class="form-field min-w-[9rem] flex-1">
            <span class="form-field__label">{{ __('expenses.to') }}</span>
            <input type="date" wire:model.live="dateTo" class="form-field__control">
        </label>
        <label class="form-field min-w-[9rem] flex-1">
            <span class="form-field__label">{{ __('expenses.category_filter') }}</span>
            <select wire:model.live="category" class="form-field__control">
                <option value="">{{ __('expenses.all_categories') }}</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->value }}">{{ __('expenses.categories.'.$cat->value) }}</option>
                @endforeach
            </select>
        </label>
        @if ($showBranchSelector)
            <label class="form-field min-w-[9rem] flex-1">
                <span class="form-field__label">{{ __('expenses.branch') }}</span>
                <select wire:model.live="branchId" class="form-field__control">
                    <option value="">{{ __('expenses.all_branches') }}</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
    </section>

    <p class="mt-4 text-sm font-medium tabular-nums">
        {{ __('expenses.filtered_total') }}: {{ number_format($totalExVat, 2) }} SAR
    </p>

    @if ($showForm)
        <form wire:submit="saveExpense" class="app-card mt-4 space-y-3 p-4">
            <h2 class="text-sm font-semibold">{{ __('expenses.add') }}</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="form-field">
                    <span class="form-field__label">{{ __('expenses.spent_on') }}</span>
                    <input type="date" wire:model="spentOn" class="form-field__control">
                </label>
                <label class="form-field">
                    <span class="form-field__label">{{ __('expenses.category') }}</span>
                    <select wire:model="formCategory" class="form-field__control">
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->value }}">{{ __('expenses.categories.'.$cat->value) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-field">
                    <span class="form-field__label">{{ __('expenses.vendor') }}</span>
                    <input type="text" wire:model="vendor" class="form-field__control">
                </label>
                <label class="form-field">
                    <span class="form-field__label">{{ __('expenses.amount_ex_vat') }}</span>
                    <input type="number" step="0.01" min="0" wire:model="amountExVat" class="form-field__control">
                </label>
                <label class="form-field sm:col-span-2">
                    <span class="form-field__label">{{ __('expenses.description') }}</span>
                    <textarea wire:model="description" rows="2" class="form-field__control"></textarea>
                </label>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="tap-action min-h-11 px-4 text-sm">{{ __('expenses.save') }}</button>
                <button type="button" wire:click="$set('showForm', false)" class="tap-action-muted min-h-11 px-4 text-sm">
                    {{ __('expenses.cancel') }}
                </button>
            </div>
        </form>
    @endif

    <div class="app-card mt-4 overflow-x-auto p-0">
        <table class="w-full min-w-[36rem] text-left text-sm">
            <thead>
                <tr class="border-b border-[var(--app-border)] bg-[var(--app-surface-muted)]/60 text-xs uppercase text-[var(--app-muted)]">
                    <th class="px-4 py-3">{{ __('expenses.spent_on') }}</th>
                    <th class="px-4 py-3">{{ __('expenses.category') }}</th>
                    <th class="px-4 py-3">{{ __('expenses.vendor') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('expenses.amount_ex_vat') }}</th>
                    @if ($canManage)
                        <th class="px-4 py-3"></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($expenses as $expense)
                    <tr class="border-b border-[var(--app-border)] last:border-0" wire:key="expense-{{ $expense->id }}">
                        <td class="px-4 py-3 tabular-nums">{{ $expense->spent_on->toDateString() }}</td>
                        <td class="px-4 py-3">{{ __('expenses.categories.'.$expense->category) }}</td>
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ $expense->vendor ?? '—' }}</span>
                            @if ($expense->description)
                                <p class="mt-0.5 text-xs text-[var(--app-muted)]">{{ $expense->description }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-end tabular-nums font-medium">{{ number_format($expense->amount_ex_vat, 2) }}</td>
                        @if ($canManage)
                            <td class="px-4 py-3 text-end">
                                <button
                                    type="button"
                                    wire:click="deleteExpense({{ $expense->id }})"
                                    wire:confirm="{{ __('expenses.confirm_delete') }}"
                                    class="text-xs text-red-700 underline"
                                >
                                    {{ __('expenses.delete') }}
                                </button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManage ? 5 : 4 }}" class="px-4 py-8 text-center text-[var(--app-muted)]">
                            {{ __('expenses.empty') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $expenses->links() }}
    </div>
</div>