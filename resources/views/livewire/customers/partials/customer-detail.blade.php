@php
    assert(isset($customerDetail) && is_array($customerDetail));
    $contact = $customerDetail['contact'];
    $totals = $customerDetail['totals'];
    $customerId = $selected?->id;
@endphp

<div class="customer-hub-detail x-modal-form flex min-h-0 flex-1 flex-col gap-5" data-testid="customer-detail-modal">
    <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="{{ __('customers.detail_totals') }}">
        @foreach ([
            'invoiced' => __('customers.detail_total_invoiced'),
            'paid' => __('customers.detail_total_paid'),
            'due' => __('customers.detail_total_due'),
            'retention' => __('customers.detail_total_retention'),
        ] as $key => $label)
            <article class="rounded-2xl border border-[var(--app-border)] bg-[var(--app-surface-muted)]/40 p-3">
                <p class="text-xs font-medium text-[var(--app-muted)]">{{ $label }}</p>
                <p class="mt-1 text-lg font-semibold tabular-nums">{{ number_format($totals[$key], 2) }}</p>
            </article>
        @endforeach
    </section>

    <dl class="customer-hub-detail__summary grid gap-3 rounded-2xl border border-[var(--app-border)] bg-[var(--app-surface-muted)]/50 p-4 text-sm sm:grid-cols-2">
        <div class="space-y-1 sm:col-span-2">
            <dt class="text-xs font-medium text-[var(--app-muted)]">{{ __('customers.name') }}</dt>
            <dd class="font-semibold" dir="auto">{{ $contact['name'] }}</dd>
        </div>
        <div class="space-y-1">
            <dt class="text-xs font-medium text-[var(--app-muted)]">{{ __('customers.phone') }}</dt>
            <dd class="font-medium tabular-nums" dir="ltr">{{ $contact['phone'] ?? '—' }}</dd>
        </div>
        <div class="space-y-1">
            <dt class="text-xs font-medium text-[var(--app-muted)]">{{ __('customers.email') }}</dt>
            <dd class="font-medium truncate" dir="auto">{{ $contact['email'] ?? '—' }}</dd>
        </div>
        <div class="space-y-1">
            <dt class="text-xs font-medium text-[var(--app-muted)]">{{ __('customers.col_vat') }}</dt>
            <dd class="font-medium tabular-nums" dir="ltr">{{ $contact['vat'] ?? '—' }}</dd>
        </div>
        <div class="space-y-1">
            <dt class="text-xs font-medium text-[var(--app-muted)]">{{ __('customers.col_cr') }}</dt>
            <dd class="font-medium tabular-nums" dir="ltr">{{ $contact['cr'] ?? '—' }}</dd>
        </div>
        <div class="space-y-1 sm:col-span-2">
            <dt class="text-xs font-medium text-[var(--app-muted)]">{{ __('customers.detail_address') }}</dt>
            <dd class="font-medium leading-relaxed" dir="auto">
                @if ($customerDetail['address_lines'] === [])
                    —
                @else
                    {{ implode(' · ', $customerDetail['address_lines']) }}
                @endif
            </dd>
        </div>
    </dl>

    <div class="flex flex-wrap gap-2" data-testid="customer-detail-quick-actions">
        <button type="button" class="tap-action-primary min-h-10 px-3 text-sm" wire:click="editFromDetail">
            {{ __('customers.action_edit') }}
        </button>
        @if ($customerDetail['can_issue'] && $customerId)
            <button type="button" class="tap-action-muted min-h-10 px-3 text-sm" wire:click="goToNewInvoice({{ $customerId }})">
                {{ __('customers.detail_new_invoice') }}
            </button>
            <button type="button" class="tap-action-muted min-h-10 px-3 text-sm" wire:click="goToNewQuotation({{ $customerId }})">
                {{ __('customers.detail_new_quotation') }}
            </button>
            <button type="button" class="tap-action-muted min-h-10 px-3 text-sm" wire:click="goToRecordPayment({{ $customerId }})">
                {{ __('customers.detail_record_payment') }}
            </button>
        @endif
        <a href="{{ $customerDetail['statement_url'] }}" wire:navigate class="tap-action-muted inline-flex min-h-10 items-center px-3 text-sm">
            {{ __('customers.detail_statement') }}
        </a>
    </div>

    <section>
        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--app-muted)]">{{ __('customers.detail_recent_invoices') }}</h3>
        @if ($customerDetail['invoices'] === [])
            <p class="mt-2 text-sm text-[var(--app-muted)]">{{ __('customers.detail_no_invoices') }}</p>
        @else
            <ul class="mt-2 space-y-2 text-sm">
                @foreach ($customerDetail['invoices'] as $row)
                    <li class="rounded-lg border border-[var(--app-border)] p-3" wire:key="cust-inv-{{ $row['invoice']->id }}">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-medium">{{ $row['document_number'] }}</p>
                                <p class="text-xs text-[var(--app-muted)]">{{ $row['issued_at']?->format('Y-m-d') }}</p>
                            </div>
                            <x-hub.invoice-row-actions
                                :invoice-id="$row['invoice']->id"
                                :actions="$row['actions']"
                                :more="$row['more']"
                                :view-url="$row['view_url']"
                                :download-url="$row['print_urls']['download']"
                                :print-url="$row['print_urls']['print']"
                                :print-a4-url="$row['print_urls']['a4'] ?? null"
                                :print-cash-url="$row['print_urls']['cash'] ?? null"
                                :print-layout="$row['print_urls']['layout'] ?? 'a4'"
                                :print-back-url="$row['print_urls']['fallback_back'] ?? route('admin.customers')"
                                :record-payment-on-page="$customerDetail['can_issue']"
                            />
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section>
        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--app-muted)]">{{ __('customers.detail_recent_quotations') }}</h3>
        @if ($customerDetail['quotations'] === [])
            <p class="mt-2 text-sm text-[var(--app-muted)]">{{ __('customers.detail_no_quotations') }}</p>
        @else
            <ul class="mt-2 space-y-2 text-sm">
                @foreach ($customerDetail['quotations'] as $row)
                    <li class="rounded-lg border border-[var(--app-border)] p-3" wire:key="cust-quo-{{ $row['quotation']->id }}">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-medium">{{ $row['quotation_number'] }}</p>
                                <p class="text-xs text-[var(--app-muted)] tabular-nums">{{ number_format($row['total_inc_vat'], 2) }}</p>
                            </div>
                            <x-hub.quotation-row-actions
                                :quotation-id="$row['quotation']->id"
                                :print-url="$row['print_urls']['print']"
                                :download-url="$row['print_urls']['download']"
                                :print-back-url="$row['print_urls']['fallback_back'] ?? route('admin.customers')"
                                :can-manage="$row['can_manage']"
                            />
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section>
        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--app-muted)]">{{ __('customers.detail_recent_payments') }}</h3>
        @if ($customerDetail['payments'] === [])
            <p class="mt-2 text-sm text-[var(--app-muted)]">{{ __('customers.detail_no_payments') }}</p>
        @else
            <ul class="mt-2 space-y-1 text-xs">
                @foreach ($customerDetail['payments'] as $p)
                    <li class="flex flex-wrap justify-between gap-2 border-t border-[var(--app-border)] pt-2 first:border-t-0 first:pt-0" wire:key="cust-pay-{{ $p['payment']->id ?? $loop->index }}">
                        <span>
                            {{ $p['document_number'] }}
                            · {{ $p['method']->label() }}
                            @if ($p['for_retention'])
                                <span class="text-amber-800">({{ __('payments.retention_payment') }})</span>
                            @endif
                        </span>
                        <span class="tabular-nums text-[var(--app-muted)]">{{ number_format($p['amount'], 2) }} · {{ $p['paid_on']?->format('Y-m-d') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <div class="x-modal__footer">
        <button type="button" class="tap-action-muted min-h-11 px-4 text-sm" wire:click="closeCustomerDetailModal">
            {{ __('staff_users.cancel') }}
        </button>
    </div>
</div>