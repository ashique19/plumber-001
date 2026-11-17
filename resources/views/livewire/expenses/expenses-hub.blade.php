<div class="expenses-hub pb-24 lg:pb-8">
    <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="app-page-title">{{ __('expenses.title') }}</h1>
            <p class="app-page-lead">{{ __('expenses.subtitle') }}</p>
        </div>
        @if ($canManage)
            <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center sm:justify-end">
                <x-app.add-button
                    type="button"
                    variant="default"
                    wire:click="openCategoriesModal"
                    class="mb-0 w-full sm:w-auto"
                    data-testid="expense-categories-open"
                >
                    {{ __('expenses.manage_categories') }}
                </x-app.add-button>
                <x-app.add-button type="button" variant="primary" wire:click="openForm" class="mb-0 w-full sm:w-auto sm:inline-flex">
                    {{ __('expenses.add') }}
                </x-app.add-button>
            </div>
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
            <select wire:model.live="category" class="form-field__control" data-testid="expense-category-filter">
                <option value="">{{ __('expenses.all_categories') }}</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->slug }}">{{ $cat->name }}</option>
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

    @if ($canManage)
        <x-admin.hub-fab wire:click="openForm" :label="__('expenses.add')" />
    @endif

    <x-admin.hub-sheet :show="$showForm" :title="__('expenses.add')" close-wire="closeExpenseForm" size="xl">
        <form wire:submit="saveExpense" class="x-modal-form">
            <div class="x-modal-form__fields x-modal-form__fields--2">
                <x-field.date :label="__('expenses.spent_on')" name="spentOn" wire:model="spentOn" />
                <x-field.select :label="__('expenses.category')" name="formCategory" wire:model="formCategory" :liveValidate="false">
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->slug }}">{{ $cat->name }}</option>
                    @endforeach
                </x-field.select>
                <x-field.input :label="__('expenses.vendor')" name="vendor" wire:model="vendor" />
                <x-field.money :label="__('expenses.amount_ex_vat')" name="amountExVat" wire:model="amountExVat" />
                <x-field.textarea :label="__('expenses.description')" name="description" wire:model="description" rows="2" wrapper-class="col-span-full" />
            </div>
            <div class="x-modal__footer x-modal__footer--in-form">
                <x-modal.footer
                    cancel-wire="closeExpenseForm"
                    submit-target="saveExpense"
                    :submit-label="__('expenses.save')"
                />
            </div>
        </form>
    </x-admin.hub-sheet>

    <x-admin.hub-sheet
        :show="$showCategoriesModal"
        :title="__('expenses.categories_modal.title')"
        close-wire="closeCategoriesModal"
        size="xl"
        class="x-modal--form-sheet"
        data-testid="expense-categories-modal"
    >
        <div class="flex flex-col gap-4">
            <form wire:submit="addCategory" class="flex flex-col gap-2 sm:flex-row sm:items-end" novalidate>
                <label class="form-field min-w-0 flex-1">
                    <span class="form-field__label">{{ __('expenses.categories_modal.add_label') }}</span>
                    <input
                        type="text"
                        wire:model="newCategoryName"
                        class="form-field__control"
                        maxlength="120"
                        data-testid="expense-category-new-name"
                        placeholder="{{ __('expenses.categories_modal.name_placeholder') }}"
                    />
                    @error('newCategoryName')
                        <span class="form-field__error text-xs text-red-700">{{ $message }}</span>
                    @enderror
                </label>
                <button type="submit" class="tap-action-primary min-h-11 w-full shrink-0 px-4 sm:w-auto">
                    {{ __('expenses.categories_modal.add') }}
                </button>
            </form>

            <ul class="divide-y divide-[var(--app-border)] rounded-xl border border-[var(--app-border)]" data-testid="expense-category-list">
                @foreach ($expenseCategories as $cat)
                    <li wire:key="expense-cat-{{ $cat->id }}" class="p-3 sm:p-4">
                        @if ($editingCategoryId === $cat->id)
                            <form wire:submit="saveCategoryRename" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <input
                                    type="text"
                                    wire:model="editingCategoryName"
                                    class="form-field__control min-w-0 flex-1"
                                    maxlength="120"
                                    data-testid="expense-category-edit-name"
                                />
                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" class="tap-action-primary min-h-10 px-3 text-sm">
                                        {{ __('expenses.categories_modal.save') }}
                                    </button>
                                    <button type="button" wire:click="cancelEditCategory" class="tap-action-muted min-h-10 px-3 text-sm">
                                        {{ __('expenses.cancel') }}
                                    </button>
                                </div>
                                @error('editingCategoryName')
                                    <span class="form-field__error text-xs text-red-700 sm:col-span-full">{{ $message }}</span>
                                @enderror
                            </form>
                        @elseif ($pendingDeleteCategoryId === $cat->id)
                            <div class="space-y-3">
                                <p class="text-sm">
                                    @if ($cat->expenses_count > 0)
                                        {{ __('expenses.categories_modal.delete_in_use', ['count' => $cat->expenses_count, 'name' => $cat->name]) }}
                                    @else
                                        {{ __('expenses.categories_modal.delete_confirm', ['name' => $cat->name]) }}
                                    @endif
                                </p>
                                @if ($cat->expenses_count > 0)
                                    <x-field.select
                                        :label="__('expenses.categories_modal.merge_into')"
                                        name="mergeCategorySlug"
                                        wire:model="mergeCategorySlug"
                                        :liveValidate="false"
                                        wrapper-class="mb-0"
                                    >
                                        <option value="">{{ __('expenses.categories_modal.choose_category') }}</option>
                                        @foreach ($expenseCategories as $other)
                                            @if ($other->id !== $cat->id)
                                                <option value="{{ $other->slug }}">{{ $other->name }}</option>
                                            @endif
                                        @endforeach
                                    </x-field.select>
                                    @error('mergeCategorySlug')
                                        <span class="form-field__error text-xs text-red-700">{{ $message }}</span>
                                    @enderror
                                @endif
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        wire:click="deleteCategory"
                                        class="min-h-10 rounded-lg bg-red-700 px-3 text-sm font-medium text-white"
                                        data-testid="expense-category-delete-confirm"
                                    >
                                        {{ $cat->expenses_count > 0 ? __('expenses.categories_modal.merge_delete') : __('expenses.delete') }}
                                    </button>
                                    <button type="button" wire:click="cancelDeleteCategory" class="tap-action-muted min-h-10 px-3 text-sm">
                                        {{ __('expenses.cancel') }}
                                    </button>
                                </div>
                            </div>
                        @else
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="font-medium truncate">{{ $cat->name }}</p>
                                    <p class="text-xs text-[var(--app-muted)]">
                                        {{ __('expenses.categories_modal.usage', ['count' => $cat->expenses_count]) }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2 sm:justify-end">
                                    <button
                                        type="button"
                                        wire:click="startEditCategory({{ $cat->id }})"
                                        class="tap-action-muted min-h-9 px-3 text-xs"
                                    >
                                        {{ __('expenses.categories_modal.rename') }}
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="promptDeleteCategory({{ $cat->id }})"
                                        class="text-xs text-red-700 underline min-h-9 px-1"
                                        data-testid="expense-category-delete-{{ $cat->id }}"
                                    >
                                        {{ __('expenses.delete') }}
                                    </button>
                                </div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </x-admin.hub-sheet>

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
                        <td class="px-4 py-3">{{ \App\Support\Expenses\ExpenseCategoryCatalog::label($expense->category) }}</td>
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
        <x-admin.hub-pager :paginator="$expenses" />
    </div>
</div>