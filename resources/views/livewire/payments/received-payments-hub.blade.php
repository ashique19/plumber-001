@push('head')
    @vite(['resources/css/hub-document-table.css', 'resources/css/admin-hub-pagination.css'])
@endpush

<div class="received-payments-hub admin-hub-page pb-24 lg:pb-8" data-testid="received-payments-hub">
    <header class="mb-4">
        <h1 class="app-page-title">{{ __('payments.received.title') }}</h1>
        <p class="app-page-lead">{{ __('payments.received.subtitle') }}</p>
    </header>

    <section class="app-card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
        <label class="form-field min-w-[9rem] flex-1">
            <span class="form-field__label">{{ __('payments.received.from') }}</span>
            <input type="date" wire:model.live="dateFrom" class="form-field__control" data-testid="received-from">
        </label>
        <label class="form-field min-w-[9rem] flex-1">
            <span class="form-field__label">{{ __('payments.received.to') }}</span>
            <input type="date" wire:model.live="dateTo" class="form-field__control" data-testid="received-to">
        </label>
        <label class="form-field min-w-[9rem] flex-1">
            <span class="form-field__label">{{ __('payments.method.label') }}</span>
            <select wire:model.live="method" class="form-field__control" data-testid="received-method">
                <option value="">{{ __('payments.received.all_methods') }}</option>
                @foreach ($methodOptions as $opt)
                    <option value="{{ $opt->value }}">{{ $opt->label() }}</option>
                @endforeach
            </select>
        </label>
        <x-field.search
            :label="__('payments.received.customer_search')"
            name="customerSearch"
            wire:model.live.debounce.300ms="customerSearch"
            :placeholder="__('payments.received.customer_placeholder')"
            :clearable="trim($customerSearch) !== ''"
            clear-action="$set('customerSearch', '')"
            wrapper-class="min-w-[12rem] flex-[1.2]"
        />
    </section>

    <p class="mt-4 text-sm font-medium tabular-nums" data-testid="received-total">
        {{ __('payments.received.total') }}: {{ number_format($totalAmount, 2) }} SAR
    </p>

    @if ($payments->total() === 0)
        <x-admin.hub-empty icon="file-text" :title="__('payments.received.empty')" class="mt-4" />
    @else
        <ul class="mt-4 space-y-3 sm:hidden" data-testid="received-payment-cards">
            @foreach ($payments as $row)
                <li wire:key="pay-card-{{ $row['payment']->id }}" class="hub-doc-mobile-card">
                    <div class="hub-doc-mobile-card__head">
                        <div class="min-w-0">
                            <p class="font-semibold tabular-nums">{{ $row['paid_on']->toDateString() }}</p>
                            <p class="text-xs text-[var(--app-muted)] truncate">
                                @if ($row['view_url'])
                                    <a href="{{ $row['view_url'] }}" class="underline">{{ $row['document_number'] }}</a>
                                @else
                                    {{ $row['document_number'] }}
                                @endif
                            </p>
                        </div>
                        <span class="font-semibold tabular-nums">{{ number_format($row['amount'], 2) }}</span>
                    </div>
                    <dl class="hub-doc-mobile-card__meta">
                        <div class="hub-doc-mobile-card__meta-row">
                            <dt class="text-[var(--app-muted)]">{{ __('payments.customer') }}</dt>
                            <dd class="truncate">{{ $row['customer'] }}</dd>
                        </div>
                        <div class="hub-doc-mobile-card__meta-row">
                            <dt class="text-[var(--app-muted)]">{{ __('payments.method.label') }}</dt>
                            <dd>{{ $row['method']->label() }}</dd>
                        </div>
                    </dl>
                    <div class="hub-doc-mobile-card__actions">
                        @if ($row['view_url'])
                            <x-hub.row-action-icon
                                icon="view"
                                :label="__('payments.received.view_invoice')"
                                :href="$row['view_url']"
                                test-id="received-view-{{ $row['payment']->id }}"
                            />
                        @endif
                        @if ($row['receipt_url'])
                            <x-hub.row-action-icon
                                icon="print"
                                :label="__('payments.received.print_receipt')"
                                :href="$row['receipt_url']"
                                test-id="received-receipt-{{ $row['payment']->id }}"
                            />
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        <section class="app-card mt-4 hidden p-0 sm:block">
            <div class="admin-data-table-scroll px-0">
                <table class="admin-data-table hub-doc-table w-full text-start text-sm" data-testid="received-payments-table">
                    <thead>
                        <tr class="border-b border-[var(--app-border)] bg-[var(--app-surface-muted)]/60 text-xs uppercase text-[var(--app-muted)]">
                            <th class="px-3 py-3 font-medium">{{ __('payments.paid_on') }}</th>
                            <th class="px-3 py-3 font-medium">{{ __('payments.received.invoice_no') }}</th>
                            <th class="px-3 py-3 font-medium">{{ __('payments.customer') }}</th>
                            <th class="px-3 py-3 font-medium">{{ __('payments.method.label') }}</th>
                            <th class="px-3 py-3 text-end font-medium">{{ __('payments.amount') }}</th>
                            <th class="px-3 py-3 text-end font-medium">{{ __('payments.received.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $row)
                            <tr class="border-b border-[var(--app-border)] last:border-0 hover:bg-[var(--app-surface-muted)]/40" wire:key="pay-row-{{ $row['payment']->id }}">
                                <td class="px-3 py-2.5 tabular-nums whitespace-nowrap">{{ $row['paid_on']->toDateString() }}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap font-medium">
                                    @if ($row['view_url'])
                                        <a href="{{ $row['view_url'] }}" class="text-[var(--app-accent)] underline">{{ $row['document_number'] }}</a>
                                    @else
                                        {{ $row['document_number'] }}
                                    @endif
                                </td>
                                <td class="max-w-[14rem] px-3 py-2.5 truncate">{{ $row['customer'] }}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    {{ $row['method']->label() }}
                                    @if ($row['for_retention'])
                                        <span class="text-xs text-amber-800">({{ __('payments.retention_payment') }})</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-end tabular-nums font-medium">{{ number_format($row['amount'], 2) }}</td>
                                <td class="px-3 py-2.5 text-end">
                                    <div class="hub-doc-table__actions hub-doc-table__actions--compact inline-flex justify-end">
                                        @if ($row['view_url'])
                                            <x-hub.row-action-icon
                                                icon="view"
                                                :label="__('payments.received.view_invoice')"
                                                :href="$row['view_url']"
                                                test-id="received-view-{{ $row['payment']->id }}"
                                            />
                                        @endif
                                        @if ($row['receipt_url'])
                                            <x-hub.row-action-icon
                                                icon="print"
                                                :label="__('payments.received.print_receipt')"
                                                :href="$row['receipt_url']"
                                                test-id="received-receipt-{{ $row['payment']->id }}"
                                            />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="mt-4">
            <x-admin.hub-pager :paginator="$payments" />
        </div>
    @endif
</div>