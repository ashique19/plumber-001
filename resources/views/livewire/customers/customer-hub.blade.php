<div class="customer-hub pb-24 lg:pb-8">
    <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="app-page-title">{{ __('customers.title') }}</h1>
            <p class="app-page-lead">{{ __('customers.subtitle') }}</p>
        </div>
        <x-app.add-button type="button" wire:click="startCustomer" class="mb-0 w-full sm:ms-auto sm:w-auto">{{ __('customers.add') }}</x-app.add-button>
    </header>

    <section class="app-card grid gap-4 p-4 lg:grid-cols-5">
        <div class="space-y-3 lg:col-span-2">
            <x-form.input :label="__('customers.search')" name="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('customers.search_placeholder') }}" autocomplete="off" />
            <ul class="max-h-[32rem] space-y-2 overflow-y-auto">
                @forelse ($customers as $row)
                    <li wire:key="cust-{{ $row->id }}">
                        <button
                            type="button"
                            wire:click="selectCustomer({{ $row->id }})"
                            @class([
                                'w-full rounded-xl border p-3 text-start transition',
                                'border-[var(--cafe-caramel)] bg-[var(--cafe-caramel)]/10' => $selected?->id === $row->id,
                                'border-[var(--app-border)] hover:border-[var(--cafe-caramel)]/50' => $selected?->id !== $row->id,
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
                    <li class="text-sm text-[var(--app-muted)]">{{ __('customers.empty_list') }}</li>
                @endforelse
            </ul>
            <div>{{ $customers->links() }}</div>
        </div>

        <div class="space-y-4 lg:col-span-3">
            @if ($selected)
                <x-form.section :title="$selected->name ?: $selected->phone" icon="users">
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
                        <x-form.input type="number" :label="__('customers.adjust_points')" name="adjustPoints" wire:model.blur="adjustPoints" placeholder="±10" />
                        <x-form.input :label="__('customers.adjust_note')" name="adjustNote" wire:model.blur="adjustNote" wrapperClass="sm:col-span-2" />
                        <x-ui.livewire-action type="submit" target="saveAdjust" loading="saving" class="tap-action-muted min-h-11 text-sm sm:col-span-2 sm:w-auto">
                            {{ __('customers.adjust_save') }}
                        </x-ui.livewire-action>
                    </form>
                </x-form.section>

                <x-form.section :title="__('customers.ledger_title')" icon="clipboard">
                    <ul class="max-h-80 space-y-2 overflow-y-auto text-sm">
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
                </x-form.section>
            @else
                <div class="app-card flex min-h-48 items-center justify-center p-6 text-center text-sm text-[var(--app-muted)]">
                    {{ __('customers.pick_one') }}
                </div>
            @endif
        </div>
    </section>

    @if ($showCustomerForm)
        <div class="app-modal-backdrop" wire:click.self="$set('showCustomerForm', false)">
            <div class="app-modal" role="dialog" aria-modal="true">
                <h2 class="text-lg font-semibold">{{ __('customers.add') }}</h2>
                <form class="mt-4 grid gap-3" wire:submit="saveCustomer">
                    <x-form.input :label="__('customers.name')" name="customerName" wire:model.blur="customerName" required />
                    <x-form.input :label="__('customers.phone')" name="customerPhone" wire:model.blur="customerPhone" required inputmode="tel" />
                    <x-form.input type="email" :label="__('customers.email_optional')" name="customerEmail" wire:model.blur="customerEmail" />
                    <div class="flex flex-wrap gap-2 pt-2">
                        <x-ui.livewire-action type="submit" target="saveCustomer" loading="saving" class="min-h-11 flex-1">{{ __('customers.save') }}</x-ui.livewire-action>
                        <button type="button" class="tap-action-muted min-h-11 flex-1" wire:click="$set('showCustomerForm', false)">{{ __('catalog.cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
