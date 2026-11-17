@if ($editingCustomerId)
    <div class="customer-hub-modal__tabs shrink-0 border-b border-[var(--app-border)] bg-white px-5 py-3" data-testid="customer-modal-tabs">
        <div class="app-section-tabs app-section-tabs--scroll min-w-0 max-w-full">
            <button
                type="button"
                wire:click="setCustomerModalTab('details')"
                @class(['app-segment-tab', 'app-segment-tab--active' => $customerModalTab === 'details'])
            >
                {{ __('customers.tab_details') }}
            </button>
            <button
                type="button"
                wire:click="setCustomerModalTab('loyalty')"
                @class(['app-segment-tab', 'app-segment-tab--active' => $customerModalTab === 'loyalty'])
            >
                {{ __('customers.tab_loyalty') }}
            </button>
        </div>
    </div>
@endif

@if (! $editingCustomerId || $customerModalTab === 'details')
    <form class="customer-hub-modal x-modal-form" wire:submit="saveCustomer" data-testid="customer-modal">
        <div class="x-modal-form__fields">
            <x-customers.identity-fields
                :fields="\App\Support\Customers\CustomerIdentityRules::hubFields()"
                more-address-prop="customerShowMoreAddress"
            />
        </div>

        <div class="x-modal__footer x-modal__footer--in-form">
            <x-modal.footer
                cancel-wire="closeCustomerModal"
                submit-target="saveCustomer"
                :submit-label="$editingCustomerId ? __('customers.save_changes') : __('customers.save')"
            />
        </div>
    </form>
@else
    <div class="customer-hub-modal x-modal-form flex min-h-0 flex-1 flex-col" data-testid="customer-modal">
        <div class="x-modal-form__fields min-h-0 flex-1 gap-5">
            <dl class="customer-hub-modal__summary grid gap-3 rounded-2xl border border-[var(--app-border)] bg-[var(--app-surface-muted)]/50 p-4 text-sm sm:grid-cols-2">
                <div class="space-y-1">
                    <dt class="text-xs font-medium text-[var(--app-muted)]">{{ __('customers.phone') }}</dt>
                    <dd class="font-semibold tabular-nums" dir="ltr">{{ $selected?->phone ?: '—' }}</dd>
                </div>
                <div class="space-y-1">
                    <dt class="text-xs font-medium text-[var(--app-muted)]">{{ __('customers.points_balance') }}</dt>
                    <dd class="font-semibold tabular-nums">{{ number_format($selected?->points_balance ?? 0) }}</dd>
                </div>
                <div class="space-y-1">
                    <dt class="text-xs font-medium text-[var(--app-muted)]">{{ __('customers.referral_code') }}</dt>
                    <dd class="font-mono text-sm">{{ $selected?->referral_code ?: '—' }}</dd>
                </div>
                <div class="space-y-1">
                    <dt class="text-xs font-medium text-[var(--app-muted)]">{{ __('customers.referred_by') }}</dt>
                    <dd class="font-medium" dir="auto">{{ $selected?->referredBy?->name ?? '—' }}</dd>
                </div>
            </dl>

            <p class="text-xs leading-relaxed text-[var(--app-muted)]">
                {{ __('customers.rates_hint', [
                    'earn' => $loyaltyRates['earn'],
                    'redeem' => $loyaltyRates['redeem'],
                    'min' => $loyaltyRates['minRedeem'],
                ]) }}
            </p>

            <form class="grid gap-4 sm:grid-cols-2" wire:submit="saveAdjust">
                <x-field.input
                    type="number"
                    :label="__('customers.adjust_points')"
                    name="adjustPoints"
                    wire:model.blur="adjustPoints"
                    placeholder="±10"
                />
                <x-field.input
                    :label="__('customers.adjust_note')"
                    name="adjustNote"
                    wire:model.blur="adjustNote"
                    wrapperClass="sm:col-span-2"
                />
                <x-ui.livewire-action
                    type="submit"
                    target="saveAdjust"
                    loading="saving"
                    class="tap-action-primary min-h-11 text-sm sm:col-span-2 sm:w-auto sm:justify-self-start"
                >
                    {{ __('customers.adjust_save') }}
                </x-ui.livewire-action>
            </form>

            <section>
                <h3 class="text-sm font-semibold">{{ __('customers.ledger_title') }}</h3>
                <ul class="mt-3 max-h-64 space-y-2 overflow-y-auto overscroll-contain text-sm">
                    @forelse ($transactions as $tx)
                        <li
                            class="app-inset-panel flex flex-wrap items-baseline justify-between gap-2 !p-3"
                            wire:key="tx-{{ $tx->id }}"
                        >
                            <div class="min-w-0">
                                <span class="pos-status-pill">{{ __('customers.tx_type.'.$tx->type->value) }}</span>
                                <span class="ms-2 text-xs text-[var(--app-muted)]">{{ $tx->occurred_at?->format('Y-m-d H:i') }}</span>
                                @if (data_get($tx->metadata, 'reason'))
                                    <span class="ms-2 text-xs" dir="auto">{{ data_get($tx->metadata, 'reason') }}</span>
                                @endif
                            </div>
                            <span @class([
                                'shrink-0 font-semibold tabular-nums',
                                'text-emerald-700' => $tx->points_delta > 0,
                                'text-rose-700' => $tx->points_delta < 0,
                            ])>
                                {{ $tx->points_delta > 0 ? '+' : '' }}{{ $tx->points_delta }}
                            </span>
                        </li>
                    @empty
                        <li class="text-[var(--app-muted)]">{{ __('customers.ledger_empty') }}</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
@endif