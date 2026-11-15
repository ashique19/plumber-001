<div class="customer-hub pb-24 lg:pb-8">
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
            <ul class="admin-hub-list" role="list">
                @forelse ($customers as $row)
                    <li wire:key="cust-{{ $row->id }}">
                        <button
                            type="button"
                            wire:click="selectCustomer({{ $row->id }})"
                            @class([
                                'h-full w-full rounded-xl border p-3 text-start transition',
                                'border-[var(--plumber-safety)] bg-[var(--plumber-safety)]/10' => $selected?->id === $row->id,
                                'border-[var(--app-border)] hover:border-[var(--plumber-safety)]/50' => $selected?->id !== $row->id,
                            ])
                        >
                            <p class="font-medium">{{ $row->name ?: __('customers.guest_label') }}</p>
                            <p class="text-xs text-[var(--app-muted)]">{{ $row->phone }} @if($row->email)· {{ $row->email }}@endif</p>
                            <p class="mt-2 flex flex-wrap gap-2 text-xs">
                                <span class="pos-status-pill pos-status-pill--paid">{{ __('customers.points', ['count' => $row->points_balance]) }}</span>
                                @if ($row->referral_code)
                                    <span class="pos-status-pill">{{ __('customers.referral_code') }} {{ $row->referral_code }}</span>
                                @endif
                            </p>
                        </button>
                    </li>
                @empty
                    <li class="col-span-full text-sm text-[var(--app-muted)]">{{ __('customers.empty_list') }}</li>
                @endforelse
            </ul>
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
        <x-admin.hub-sheet :show="true" :title="__('customers.add')" close-wire="$set('showCustomerForm', false)">
            <form class="x-modal-form" wire:submit="saveCustomer">
                <div class="x-modal-form__fields">
                    <x-field.input :label="__('customers.name')" name="customerName" wire:model.blur="customerName" required />
                    <x-field.input :label="__('customers.phone')" name="customerPhone" wire:model.blur="customerPhone" required inputmode="tel" icon="phone" />
                    <x-field.input type="email" :label="__('customers.email_optional')" name="customerEmail" wire:model.blur="customerEmail" icon="mail" />
                </div>
                <div class="x-modal__footer x-modal__footer--in-form">
                    <x-modal.footer
                        cancel-wire="$set('showCustomerForm', false)"
                        submit-target="saveCustomer"
                        :submit-label="__('customers.save')"
                    />
                </div>
            </form>
        </x-admin.hub-sheet>
    @endif
</div>