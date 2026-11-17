<div class="payments-dues min-w-0 max-w-full overflow-x-hidden pb-24 lg:pb-8">
    <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="app-page-title">{{ __('payments.title') }}</h1>
            <p class="app-page-lead">{{ __('payments.subtitle') }}</p>
        </div>
        <div class="w-full min-w-0 sm:max-w-sm">
            <x-app.stat-card
                :label="__('payments.total_outstanding')"
                :value="number_format($totalOutstanding, 2).' '.__('dashboard.sar')"
                icon="chart"
            />
        </div>
    </header>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="app-card p-4" aria-label="{{ __('payments.outstanding_customers') }}">
            <h2 class="text-sm font-semibold">{{ __('payments.outstanding_customers') }}</h2>
            <p class="mt-1 text-xs text-[var(--app-muted)]">{{ __('payments.outstanding_hint') }}</p>

            @if ($customers->total() === 0)
                <p class="mt-4 text-sm text-[var(--app-muted)]" data-testid="dues-customer-empty">{{ __('payments.no_outstanding') }}</p>
            @else
                <ul class="mt-4 space-y-2 sm:hidden" data-testid="dues-customer-cards">
                    @foreach ($customers as $row)
                        <li
                            wire:key="cust-card-{{ $row['key'] }}"
                            class="rounded-xl border border-[var(--app-border)] p-3"
                            wire:click="selectCustomer('{{ $row['key'] }}')"
                        >
                            <p class="font-medium">{{ $row['name'] }}</p>
                            <dl class="mt-2 grid grid-cols-1 gap-1 text-xs">
                                <div class="flex justify-between gap-2">
                                    <dt class="text-[var(--app-muted)]">{{ __('payments.due_now') }}</dt>
                                    <dd class="tabular-nums font-semibold">{{ number_format($row['principal_outstanding'], 2) }}</dd>
                                </div>
                                <div class="flex justify-between gap-2">
                                    <dt class="text-[var(--app-muted)]">{{ __('payments.retention_due') }}</dt>
                                    <dd class="tabular-nums font-semibold text-amber-800">{{ number_format($row['retention_outstanding'], 2) }}</dd>
                                </div>
                            </dl>
                        </li>
                    @endforeach
                </ul>
                <div class="-mx-4 mt-4 hidden overflow-x-auto overscroll-x-contain px-4 sm:block" data-testid="dues-customer-table">
                    <table class="w-full min-w-[28rem] text-left text-sm">
                        <thead>
                            <tr class="border-b border-[var(--app-border)] text-xs text-[var(--app-muted)]">
                                <th class="pb-2 pe-2 font-medium">{{ __('payments.customer') }}</th>
                                <th class="pb-2 px-2 text-end font-medium">{{ __('payments.due_now') }}</th>
                                <th class="pb-2 ps-2 text-end font-medium">{{ __('payments.retention_due') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($customers as $row)
                                <tr
                                    wire:key="cust-{{ $row['key'] }}"
                                    @class([
                                        'border-b border-[var(--app-border)] cursor-pointer hover:bg-[var(--app-surface-muted)]/50',
                                        'bg-[var(--app-surface-muted)]/80' => $customerKey === $row['key'],
                                    ])
                                    wire:click="selectCustomer('{{ $row['key'] }}')"
                                >
                                    <td class="py-2.5 pe-2">
                                        <p class="font-medium">{{ $row['name'] }}</p>
                                        <p class="text-xs text-[var(--app-muted)]">
                                            {{ $row['invoice_count'] }} {{ __('payments.invoices') }}
                                            @if ($row['phone'])
                                                · {{ $row['phone'] }}
                                            @endif
                                        </p>
                                    </td>
                                    <td class="py-2.5 px-2 text-end tabular-nums">{{ number_format($row['principal_outstanding'], 2) }}</td>
                                    <td class="py-2.5 ps-2 text-end tabular-nums text-amber-800">{{ number_format($row['retention_outstanding'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-admin.hub-pager :paginator="$customers" />
            @endif
        </section>

        <section class="app-card p-4" aria-label="{{ __('payments.statement') }}">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">{{ __('payments.statement') }}</h2>
                @if ($statement)
                    <button type="button" class="tap-action-muted min-h-9 px-3 text-xs" wire:click="clearCustomer">
                        {{ __('payments.clear_statement') }}
                    </button>
                @endif
            </div>

            @if (! $statement)
                <p class="mt-4 text-sm text-[var(--app-muted)]">{{ __('payments.statement_hint') }}</p>
            @else
                <div class="mt-3 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-muted)]/40 p-3 text-sm">
                    <p class="font-semibold">{{ $statement['customer']['name'] }}</p>
                    @if ($statement['customer']['phone'])
                        <p class="text-xs text-[var(--app-muted)]">{{ $statement['customer']['phone'] }}</p>
                    @endif
                </div>

                <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-[var(--app-muted)]">{{ __('payments.open_invoices') }}</h3>
                <ul class="mt-2 space-y-2 text-sm">
                    @foreach ($statement['invoices'] as $row)
                        <li class="rounded-lg border border-[var(--app-border)] p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-medium">{{ $row['document_number'] }}</span>
                                <span class="rounded-full bg-[var(--app-surface-muted)] px-2 py-0.5 text-xs font-medium">
                                    {{ $row['status']->label() }}
                                </span>
                            </div>
                            <dl class="mt-2 grid gap-1 text-xs sm:grid-cols-2">
                                <div class="flex justify-between gap-2">
                                    <dt class="text-[var(--app-muted)]">{{ __('payments.due_now') }}</dt>
                                    <dd class="tabular-nums font-medium">{{ number_format($row['principal_outstanding'], 2) }}</dd>
                                </div>
                                <div class="flex justify-between gap-2">
                                    <dt class="text-[var(--app-muted)]">{{ __('payments.retention_due') }}</dt>
                                    <dd class="tabular-nums font-medium text-amber-800">{{ number_format($row['retention_outstanding'], 2) }}</dd>
                                </div>
                            </dl>
                        </li>
                    @endforeach
                </ul>

                @if ($statement['payments'] !== [])
                    <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-[var(--app-muted)]">{{ __('payments.payment_history') }}</h3>
                    <ul class="mt-2 space-y-1 text-xs">
                        @foreach ($statement['payments'] as $p)
                            <li class="flex flex-wrap justify-between gap-2 border-t border-[var(--app-border)] pt-2 first:border-t-0 first:pt-0">
                                <span>
                                    {{ $p['document_number'] }}
                                    · {{ $p['method']->label() }}
                                    @if ($p['for_retention'])
                                        <span class="text-amber-800">({{ __('payments.retention_payment') }})</span>
                                    @endif
                                </span>
                                <span class="tabular-nums font-medium">{{ number_format($p['amount'], 2) }} · {{ $p['paid_on']?->format('Y-m-d') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </section>
    </div>

    @if ($canRecord)
        <section class="app-card mt-4 p-4" aria-label="{{ __('payments.record_payment') }}">
            <h2 class="text-sm font-semibold">{{ __('payments.record_payment') }}</h2>
            <p class="mt-1 text-xs text-[var(--app-muted)]">{{ __('payments.record_hint') }}</p>

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <div>
                    <x-field.input
                        type="search"
                        :label="__('payments.invoice_search')"
                        name="invoiceSearch"
                        wire:model.live.debounce.300ms="invoiceSearch"
                    />
                    <ul class="mt-2 max-h-64 space-y-1 overflow-y-auto text-sm">
                        @foreach ($invoicePicklist as $inv)
                            <li>
                                <button
                                    type="button"
                                    wire:click="selectInvoice({{ $inv['id'] }})"
                                    @class([
                                        'w-full rounded-lg border px-3 py-2 text-left',
                                        'border-[var(--app-accent)] bg-[var(--app-surface-muted)]' => $selectedInvoiceId === $inv['id'],
                                        'border-[var(--app-border)]' => $selectedInvoiceId !== $inv['id'],
                                    ])
                                >
                                    <span class="font-medium">{{ $inv['document_number'] }}</span>
                                    <span class="ms-2 text-xs text-[var(--app-muted)]">{{ $inv['status']->label() }}</span>
                                    <span class="mt-1 block text-xs tabular-nums">
                                        {{ __('payments.due_now') }} {{ number_format($inv['principal_outstanding'], 2) }}
                                        @if ($inv['retention_outstanding'] > 0)
                                            · {{ __('payments.retention_due') }} {{ number_format($inv['retention_outstanding'], 2) }}
                                        @endif
                                    </span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                    <x-admin.hub-pager :paginator="$invoicePicklist" />
                </div>

                <form class="space-y-3" wire:submit="recordPayment">
                    @if ($selectedBalance)
                        <div class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-muted)]/50 p-3 text-xs">
                            <p>{{ __('payments.due_now') }}: <span class="tabular-nums font-semibold">{{ number_format($selectedBalance['principal_outstanding'], 2) }}</span></p>
                            <p class="mt-1">{{ __('payments.retention_due') }}: <span class="tabular-nums font-semibold text-amber-800">{{ number_format($selectedBalance['retention_outstanding'], 2) }}</span></p>
                        </div>
                    @endif

                    <x-field.input type="date" :label="__('payments.paid_on')" name="paidOn" wire:model="paidOn" />
                    <x-field.input type="number" step="0.01" min="0.01" :label="__('payments.amount')" name="paymentAmount" wire:model="paymentAmount" />
                    <x-field.select :label="__('payments.method.label')" name="paymentMethod" wire:model="paymentMethod">
                        <option value="cash">{{ __('payments.method.cash') }}</option>
                        <option value="bank">{{ __('payments.method.bank') }}</option>
                        <option value="card">{{ __('payments.method.card') }}</option>
                    </x-field.select>
                    <x-field.input :label="__('payments.reference')" name="paymentReference" wire:model="paymentReference" />
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" class="rounded border-[var(--app-border)]" wire:model="forRetention" />
                        {{ __('payments.apply_to_retention') }}
                    </label>
                    <button type="submit" class="tap-action-primary min-h-11 w-full px-4 text-sm font-semibold" @disabled(! $selectedInvoiceId)>
                        {{ __('payments.save_payment') }}
                    </button>
                </form>
            </div>
        </section>
    @endif
</div>