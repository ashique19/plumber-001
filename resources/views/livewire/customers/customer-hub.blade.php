<div class="customer-hub pb-24 lg:pb-8" data-testid="customers-hub">
    <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="app-page-title">{{ __('customers.title') }}</h1>
            <p class="app-page-lead">{{ __('customers.subtitle') }}</p>
        </div>
        <x-app.add-button type="button" variant="primary" wire:click="startCustomer" class="mb-0 hidden w-full sm:ms-auto sm:inline-flex sm:w-auto">
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
                :placeholder="__('customers.search_placeholder')"
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
                                $displayName = $row->name ?: __('customers.guest_label');
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
                                    <div class="inline-flex flex-wrap items-center justify-end gap-1">
                                        <button type="button" class="tap-action-muted min-h-9 px-2 text-xs" wire:click="selectCustomer({{ $row->id }})">
                                            {{ __('customers.action_view') }}
                                        </button>
                                        <button type="button" class="tap-action-muted min-h-9 px-2 text-xs" wire:click="startEdit({{ $row->id }})">
                                            {{ __('customers.action_edit') }}
                                        </button>
                                        <button
                                            type="button"
                                            class="tap-action-muted min-h-9 px-2 text-xs text-red-700"
                                            wire:click="deleteCustomer({{ $row->id }})"
                                            wire:confirm="{{ __('customers.confirm_delete') }}"
                                        >
                                            {{ __('customers.action_delete') }}
                                        </button>
                                    </div>
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

            <div>{{ $customers->links() }}</div>
        </section>
    @endif

    <x-admin.hub-fab wire:click="startCustomer" :label="__('customers.add')" />

    <x-admin.hub-sheet
        :show="$showDetailSheet && $selected"
        :title="$selected?->name ?: $selected?->phone ?? __('customers.title')"
        close-wire="closeDetailSheet"
    >
        @if ($selected)
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-[var(--app-muted)]">{{ __('customers.phone') }}</dt>
                    <dd class="font-medium">{{ $selected->phone }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-[var(--app-muted)]">{{ __('customers.points_balance') }}</dt>
                    <dd class="font-medium">{{ number_format($selected->points_balance) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-[var(--app-muted)]">{{ __('customers.referral_code') }}</dt>
                    <dd class="font-mono text-sm">{{ $selected->referral_code ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-[var(--app-muted)]">{{ __('customers.referred_by') }}</dt>
                    <dd class="font-medium">{{ $selected->referredBy?->name ?? '—' }}</dd>
                </div>
            </dl>

            <p class="mt-4 text-xs text-[var(--app-muted)]">
                {{ __('customers.rates_hint', [
                    'earn' => $loyaltyRates['earn'],
                    'redeem' => $loyaltyRates['redeem'],
                    'min' => $loyaltyRates['minRedeem'],
                ]) }}
            </p>

            <form class="mt-4 grid gap-3 sm:grid-cols-2" wire:submit="saveAdjust">
                <x-field.input type="number" :label="__('customers.adjust_points')" name="adjustPoints" wire:model.blur="adjustPoints" placeholder="±10" />
                <x-field.input :label="__('customers.adjust_note')" name="adjustNote" wire:model.blur="adjustNote" wrapperClass="sm:col-span-2" />
                <x-ui.livewire-action type="submit" target="saveAdjust" loading="saving" class="tap-action-primary min-h-11 text-sm sm:col-span-2 sm:w-auto">
                    {{ __('customers.adjust_save') }}
                </x-ui.livewire-action>
            </form>

            <h3 class="mt-6 text-sm font-semibold">{{ __('customers.ledger_title') }}</h3>
            <ul class="mt-2 max-h-80 space-y-2 overflow-y-auto text-sm">
                @forelse ($transactions as $tx)
                    <li class="app-inset-panel flex flex-wrap items-baseline justify-between gap-2 !p-3" wire:key="tx-{{ $tx->id }}">
                        <div>
                            <span class="pos-status-pill">{{ __('customers.tx_type.'.$tx->type->value) }}</span>
                            <span class="ms-2 text-xs text-[var(--app-muted)]">{{ $tx->occurred_at?->format('Y-m-d H:i') }}</span>
                            @if (data_get($tx->metadata, 'reason'))
                                <span class="ms-2 text-xs">{{ data_get($tx->metadata, 'reason') }}</span>
                            @endif
                        </div>
                        <span @class(['font-semibold', 'text-emerald-700' => $tx->points_delta > 0, 'text-rose-700' => $tx->points_delta < 0])>
                            {{ $tx->points_delta > 0 ? '+' : '' }}{{ $tx->points_delta }}
                        </span>
                    </li>
                @empty
                    <li class="text-[var(--app-muted)]">{{ __('customers.ledger_empty') }}</li>
                @endforelse
            </ul>
        @endif
    </x-admin.hub-sheet>

    @if ($showCustomerForm)
        <x-admin.hub-sheet
            :show="true"
            :title="$editingCustomerId ? __('customers.edit_title') : __('customers.add')"
            close-wire="$set('showCustomerForm', false)"
        >
            <form class="x-modal-form" wire:submit="saveCustomer">
                <div class="x-modal-form__fields">
                    <x-field.input :label="__('customers.name')" name="customerName" wire:model.blur="customerName" required />
                    <x-field.input :label="__('customers.phone')" name="customerPhone" wire:model.blur="customerPhone" required inputmode="tel" icon="phone" />
                    <x-field.input type="email" :label="__('customers.email_optional')" name="customerEmail" wire:model.blur="customerEmail" icon="mail" />
                    <x-field.input :label="__('customers.col_vat')" name="customerVat" wire:model.blur="customerVat" inputmode="numeric" />
                    <x-field.input :label="__('customers.col_cr')" name="customerCr" wire:model.blur="customerCr" inputmode="numeric" />
                    <x-field.input :label="__('customers.col_city')" name="customerCity" wire:model.blur="customerCity" />
                </div>
                <div class="x-modal__footer x-modal__footer--in-form">
                    <x-modal.footer
                        cancel-wire="$set('showCustomerForm', false)"
                        submit-target="saveCustomer"
                        :submit-label="$editingCustomerId ? __('customers.save_changes') : __('customers.save')"
                    />
                </div>
            </form>
        </x-admin.hub-sheet>
    @endif
</div>