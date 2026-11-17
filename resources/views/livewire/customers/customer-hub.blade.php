<div class="customer-hub admin-hub-page pb-24 lg:pb-8" data-testid="customers-hub">
    <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="app-page-title">
                {{ $loyaltyFeatureEnabled ? __('customers.title_with_loyalty') : __('customers.title_hub') }}
            </h1>
            <p class="app-page-lead">
                {{ $loyaltyFeatureEnabled ? __('customers.subtitle_with_loyalty') : __('customers.subtitle_hub') }}
            </p>
        </div>
        <x-app.add-button type="button" variant="primary" data-admin-livewire-call="startCustomer" class="mb-0 hidden w-full sm:ms-auto sm:inline-flex sm:w-auto">
            {{ __('customers.add') }}
        </x-app.add-button>
    </header>

    @php
        $listEmpty = $customers->total() === 0 && trim($search) === '';
    @endphp

    @if ($listEmpty)
        <x-admin.hub-empty icon="users" :title="__('customers.empty_list')" :action-label="__('customers.add')" action-wire="startCustomer" />
    @else
        <section class="app-card space-y-4 p-4">
            <x-field.search
                :label="__('customers.search')"
                name="search"
                wire:model.live.debounce.300ms="search"
                :placeholder="$loyaltyFeatureEnabled ? __('customers.search_placeholder_with_loyalty') : __('customers.search_placeholder_hub')"
                :clearable="trim($search) !== ''"
                clear-action="$set('search', '')"
            />

            <div class="admin-data-table-scroll -mx-4 px-4">
                <table class="admin-data-table text-start text-sm" data-testid="customers-table">
                    <thead>
                        <tr class="border-b border-[var(--app-border)] bg-[var(--app-surface-muted)]/60 text-xs uppercase text-[var(--app-muted)]">
                            @php
                                $columns = [
                                    'name' => __('customers.name'),
                                    'phone' => __('customers.phone'),
                                    'email' => __('customers.email'),
                                    'vat_number' => __('customers.col_vat'),
                                    'cr_number' => __('customers.col_cr'),
                                    'city' => __('customers.col_city'),
                                    'outstanding' => __('customers.col_outstanding'),
                                ];
                            @endphp
                            @foreach ($columns as $sortKey => $label)
                                <th
                                    @class([
                                        'admin-data-table__sticky px-3 py-3 font-medium' => $sortKey === 'name',
                                        'px-3 py-3 font-medium whitespace-nowrap' => $sortKey !== 'name',
                                        'text-end tabular-nums' => $sortKey === 'outstanding',
                                    ])
                                    scope="col"
                                    aria-sort="{{ $sort === $sortKey ? ($sortDir === 'asc' ? 'ascending' : 'descending') : 'none' }}"
                                >
                                    @if ($sortKey === 'outstanding' || array_key_exists($sortKey, $columns))
                                        <button
                                            type="button"
                                            class="inline-flex max-w-full items-center gap-1 text-start hover:text-[var(--app-text)]"
                                            wire:click="sortBy('{{ $sortKey }}')"
                                        >
                                            <span class="truncate">{{ $label }}</span>
                                            @if ($sort === $sortKey)
                                                <x-app.icon
                                                    name="chevron"
                                                    @class([
                                                        'h-3.5 w-3.5 shrink-0 opacity-70 -rotate-90' => $sortDir === 'asc',
                                                        'h-3.5 w-3.5 shrink-0 opacity-70 rotate-90' => $sortDir === 'desc',
                                                    ])
                                                />
                                            @endif
                                        </button>
                                    @endif
                                </th>
                            @endforeach
                            <th class="px-3 py-3 text-end font-medium" scope="col">{{ __('customers.col_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $row)
                            @php
                                $displayName = \App\Support\Customers\CustomerDisplayName::for($row);
                                $cr = \App\Support\Customers\CustomerProfileFields::crNumber($row);
                                $city = \App\Support\Customers\CustomerProfileFields::city($row);
                                $outstanding = $outstandingById[$row->id] ?? 0.0;
                            @endphp
                            <tr class="border-b border-[var(--app-border)] last:border-0 hover:bg-[var(--app-surface-muted)]/40" wire:key="cust-{{ $row->id }}">
                                <td class="admin-data-table__sticky max-w-[11rem] px-3 py-2.5 font-medium">
                                    <span class="line-clamp-2" dir="auto">{{ $displayName }}</span>
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap tabular-nums" dir="ltr">{{ $row->phone ?: '—' }}</td>
                                <td class="max-w-[12rem] px-3 py-2.5 truncate" dir="auto">{{ $row->email ?: '—' }}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap tabular-nums" dir="ltr">{{ $row->vat_number ?: '—' }}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap tabular-nums" dir="ltr">{{ $cr ?: '—' }}</td>
                                <td class="max-w-[8rem] px-3 py-2.5 truncate" dir="auto">{{ $city ?: '—' }}</td>
                                <td class="px-3 py-2.5 text-end tabular-nums whitespace-nowrap">
                                    @if ($outstanding > 0)
                                        {{ number_format($outstanding, 2) }}
                                    @else
                                        <span class="text-[var(--app-muted)]">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-end">
                                    <x-hub.customer-row-actions :customer-id="$row->id" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-8 text-center text-[var(--app-muted)]">{{ __('customers.search_empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-admin.hub-pager :paginator="$customers" />
        </section>
    @endif

    <x-admin.hub-fab wire:click="startCustomer" :label="__('customers.add')" />

    @php
        $customerModalTitle = $editingCustomerId
            ? (($selected !== null ? \App\Support\Customers\CustomerDisplayName::for($selected) : null) ?: __('customers.edit_title'))
            : __('customers.add');
        $customerDetailTitle = $selected !== null
            ? \App\Support\Customers\CustomerDisplayName::for($selected)
            : __('customers.detail_title');
    @endphp

    <x-admin.hub-sheet
        class="customer-hub-detail-shell x-modal--form-sheet"
        size="xl"
        :show="$showCustomerDetailModal"
        :title="$customerDetailTitle"
        close-wire="closeCustomerDetailModal"
    >
        @if ($showCustomerDetailModal && $customerDetail !== null)
            @include('livewire.customers.partials.customer-detail', [
                'selected' => $selected,
                'customerDetail' => $customerDetail,
            ])
        @endif
    </x-admin.hub-sheet>

    <x-admin.hub-sheet
        class="customer-hub-modal-shell x-modal--form-sheet x-modal--identity"
        size="identity"
        :show="$showCustomerModal"
        :title="$customerModalTitle"
        close-wire="closeCustomerModal"
    >
        @include('livewire.customers.partials.customer-modal', [
            'selected' => $selected,
            'loyaltyRates' => $loyaltyRates,
            'transactions' => $transactions,
            'loyaltyFeatureEnabled' => $loyaltyFeatureEnabled,
        ])
    </x-admin.hub-sheet>
</div>