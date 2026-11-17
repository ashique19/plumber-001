@push('head')
    @vite(['resources/css/hub-document-table.css'])
@endpush

<div class="invoice-hub admin-hub-page pb-24 lg:pb-8">
    <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="app-page-title">{{ __('invoices.title') }}</h1>
            <p class="app-page-lead">{{ __('invoices.subtitle') }}</p>
        </div>
    </header>

    @if ($canIssueInvoice ?? false)
        <section class="app-card mb-4 space-y-3 p-4" aria-label="{{ __('invoices.create_heading') }}">
            <h2 class="text-base font-semibold">{{ __('invoices.create_heading') }}</h2>
            <p class="text-sm text-[var(--app-muted)]">{{ __('invoices.create_print_hint') }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <a
                    href="{{ route('admin.invoices.issue', ['mode' => 'standard']) }}"
                    class="app-card tap-action-muted flex min-h-14 flex-col items-start justify-center gap-1 border-2 px-4 py-3 text-start"
                >
                    <span class="text-sm font-semibold">{{ __('invoices.issue_standard_title') }}</span>
                    <span class="text-xs font-medium text-[var(--app-accent)]">{{ __('invoices.print_a4') }}</span>
                </a>
                <a
                    href="{{ route('admin.invoices.issue', ['mode' => 'simplified']) }}"
                    class="app-card tap-action-muted flex min-h-14 flex-col items-start justify-center gap-1 border-2 px-4 py-3 text-start"
                >
                    <span class="text-sm font-semibold">{{ __('invoices.issue_simplified_title') }}</span>
                    <span class="text-xs font-medium text-[var(--app-accent)]">{{ __('invoices.print_cash') }}</span>
                </a>
            </div>
        </section>
    @endif

    <section class="app-card app-filter-fields mb-4 grid grid-cols-1 items-end gap-3 p-4 md:grid-cols-2 xl:grid-cols-3">
        <x-field.search
            :label="__('invoices.search')"
            name="search"
            wire:model.live.debounce.300ms="search"
            :placeholder="__('invoices.search_hint')"
            wrapper-class="min-w-0 md:col-span-2 xl:col-span-3"
        />
        <x-field.input type="date" :label="__('invoices.date_from')" name="dateFrom" wire:model.live="dateFrom" :liveValidate="false" />
        <x-field.input type="date" :label="__('invoices.date_to')" name="dateTo" wire:model.live="dateTo" :liveValidate="false" />
        <x-field.select :label="__('invoices.type_filter')" name="typeFilter" wire:model.live="typeFilter" :liveValidate="false">
            <option value="all">{{ __('invoices.filter_all') }}</option>
            <option value="standard">{{ __('invoices.type.standard') }}</option>
            <option value="simplified">{{ __('invoices.type.simplified') }}</option>
            <option value="credit_note">{{ __('invoices.type.credit_note') }}</option>
            <option value="debit_note">{{ __('invoices.type.debit_note') }}</option>
        </x-field.select>
    </section>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <nav class="hub-doc-table__tabs" aria-label="{{ __('invoices.zatca_tabs_label') }}">
            <button type="button" @class(['hub-doc-table__tab', 'hub-doc-table__tab--active' => $zatcaTab === 'all']) wire:click="$set('zatcaTab', 'all')">
                {{ __('invoices.zatca_tab_all') }}
            </button>
            <button type="button" @class(['hub-doc-table__tab', 'hub-doc-table__tab--active' => $zatcaTab === 'failed']) wire:click="$set('zatcaTab', 'failed')">
                {{ __('invoices.zatca_tab_failed') }}
                @if (($zatcaFailedCount ?? 0) > 0)
                    <span class="hub-doc-table__tab-badge">{{ $zatcaFailedCount }}</span>
                @endif
            </button>
            <button type="button" @class(['hub-doc-table__tab', 'hub-doc-table__tab--active' => $zatcaTab === 'success']) wire:click="$set('zatcaTab', 'success')">
                {{ __('invoices.zatca_tab_success') }}
                @if (($zatcaSuccessCount ?? 0) > 0)
                    <span class="hub-doc-table__tab-badge hub-doc-table__tab-badge--muted">{{ $zatcaSuccessCount }}</span>
                @endif
            </button>
        </nav>
        @if (($canResubmitZatca ?? false) && ($zatcaTab === 'failed') && ($zatcaFailedCount ?? 0) > 0)
            <button
                type="button"
                class="tap-action-muted min-h-11 px-4 text-sm font-semibold"
                wire:click="resubmitAllFailed"
                wire:loading.attr="disabled"
                wire:target="resubmitAllFailed"
            >
                {{ __('invoices.zatca_resubmit_all') }}
            </button>
        @endif
    </div>

        <x-hub.document-table-shell :aria-label="__('invoices.table_caption')">
            <thead>
                <tr>
                    <th scope="col" class="hub-doc-table__sticky">
                        <button type="button" class="hub-doc-table__sort" wire:click="sortByColumn('document_number')" @if ($sortBy === 'document_number') aria-sort="{{ $sortDir === 'asc' ? 'ascending' : 'descending' }}" @endif>
                            {{ __('invoices.col_number') }}
                        </button>
                    </th>
                    <th scope="col">
                        <button type="button" class="hub-doc-table__sort" wire:click="sortByColumn('issued_at')" @if ($sortBy === 'issued_at') aria-sort="{{ $sortDir === 'asc' ? 'ascending' : 'descending' }}" @endif>
                            {{ __('invoices.col_date') }}
                        </button>
                    </th>
                    <th scope="col">
                        <button type="button" class="hub-doc-table__sort" wire:click="sortByColumn('buyer_name')" @if ($sortBy === 'buyer_name') aria-sort="{{ $sortDir === 'asc' ? 'ascending' : 'descending' }}" @endif>
                            {{ __('invoices.col_customer') }}
                        </button>
                    </th>
                    <th scope="col">{{ __('invoices.col_total') }}</th>
                    <th scope="col">{{ __('invoices.col_due') }}</th>
                    <th scope="col">{{ __('invoices.col_payment_status') }}</th>
                    <th scope="col">{{ __('invoices.col_invoice_status') }}</th>
                    <th scope="col">{{ __('invoices.col_zatca_status') }}</th>
                    <th scope="col" class="text-end">{{ __('invoices.col_actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $row)
                    @php
                        $invoice = $row['invoice'];
                        $urls = $row['print_urls'];
                        $paymentKey = $row['payment_status']->value;
                        $a = $row['actions'];
                    @endphp
                    <tr wire:key="inv-{{ $invoice->id }}">
                        <td class="hub-doc-table__sticky font-semibold tabular-nums">
                            <div class="hub-doc-table__id-cell">
                                <span>{{ $invoice->document_number }}</span>
                                @if (($a['resend_zatca'] ?? false) && ($canResubmitZatca ?? false))
                                    <button
                                        type="button"
                                        class="hub-doc-table__action hub-doc-table__action--accent hub-doc-table__resend-inline"
                                        wire:click="resubmitInvoice({{ $invoice->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="resubmitInvoice({{ $invoice->id }})"
                                    >
                                        {{ __('invoices.action_resend_zatca') }}
                                    </button>
                                @endif
                            </div>
                        </td>
                        <td class="tabular-nums whitespace-nowrap">{{ $invoice->issued_at?->format('Y-m-d') ?? ($invoice->created_at?->format('Y-m-d') ?? '—') }}</td>
                        <td class="max-w-[12rem] truncate" title="{{ $row['customer'] }}">{{ $row['customer'] }}</td>
                        <td class="hub-doc-table__amount"><x-invoices.sar-symbol /> {{ number_format($row['total_inc_vat'], 2) }}</td>
                        <td class="hub-doc-table__amount"><x-invoices.sar-symbol /> {{ number_format($row['due_amount'], 2) }}</td>
                        <td>
                            <span @class([
                                'pos-status-pill',
                                'pos-status-pill--paid' => $paymentKey === 'paid',
                                'pos-status-pill--warning' => $paymentKey === 'partial',
                                'pos-status-pill--refunded' => $paymentKey === 'unpaid',
                            ])>{{ $row['payment_status']->label() }}</span>
                        </td>
                        <td>
                            <span @class([
                                'pos-status-pill',
                                'pos-status-pill--warning' => $row['invoice_status_key'] === 'pending',
                                'pos-status-pill--refunded' => $row['invoice_status_key'] === 'draft',
                                'pos-status-pill--paid' => $row['invoice_status_key'] === 'issued',
                            ])>{{ __('invoices.invoice_status.'.$row['invoice_status_key']) }}</span>
                        </td>
                        <td>
                            <span
                                @class([
                                    'pos-status-pill',
                                    'pos-status-pill--paid' => in_array($row['zatca_badge'], ['cleared', 'reported'], true),
                                    'pos-status-pill--warning' => $row['zatca_badge'] === 'pending',
                                    'pos-status-pill--refunded' => $row['zatca_badge'] === 'not_submitted',
                                ])
                                @if ($row['zatca_error']) title="{{ $row['zatca_error'] }}" @endif
                            >{{ __('invoices.zatca_badge.'.$row['zatca_badge']) }}</span>
                        </td>
                        <td>
                            <div class="hub-doc-table__actions">
                                @if ($a['view'] ?? false)
                                    <a class="hub-doc-table__action" href="{{ $row['view_url'] }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        {{ __('invoices.action_view') }}
                                    </a>
                                @endif
                                @if ($a['download'] ?? false)
                                    <a class="hub-doc-table__action" href="{{ $urls['download'] }}" target="_blank" rel="noopener">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
                                        {{ __('invoices.action_download_pdf') }}
                                    </a>
                                    <a class="hub-doc-table__action" href="{{ $urls['print'] }}" target="_blank" rel="noopener">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
                                        {{ __('invoices.action_print') }}
                                    </a>
                                @elseif ($a['awaiting_clearance'] ?? false)
                                    <span class="hub-doc-table__action hub-doc-table__action--disabled" title="{{ __('invoices.awaiting_zatca_clearance') }}">{{ __('invoices.action_download_pdf') }}</span>
                                    <span class="hub-doc-table__action hub-doc-table__action--disabled" title="{{ __('invoices.awaiting_zatca_clearance') }}">{{ __('invoices.action_print') }}</span>
                                @endif
                                @if (($a['resend_zatca'] ?? false) && ($canResubmitZatca ?? false))
                                    <button
                                        type="button"
                                        class="hub-doc-table__action hub-doc-table__action--accent hub-doc-table__resend-desktop"
                                        wire:click="resubmitInvoice({{ $invoice->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="resubmitInvoice({{ $invoice->id }})"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                                        {{ __('invoices.action_resend_zatca') }}
                                    </button>
                                @endif
                                <x-hub.invoice-more-menu
                                    :invoice-id="$invoice->id"
                                    :actions="$a"
                                    :more="$row['more']"
                                />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="p-6 text-center text-sm text-[var(--app-muted)]">{{ __('invoices.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </x-hub.document-table-shell>

    <div class="mt-4">{{ $invoices->links() }}</div>
</div>