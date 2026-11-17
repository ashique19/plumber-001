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

    <section class="app-card app-filter-fields mb-4 grid grid-cols-1 items-end gap-3 p-4 md:grid-cols-2 lg:grid-cols-4">
        <x-field.search
            :label="__('invoices.search')"
            name="search"
            wire:model.live.debounce.300ms="search"
            :placeholder="__('invoices.search_hint')"
            wrapper-class="min-w-0 md:col-span-2 lg:col-span-1"
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

    <nav class="hub-doc-table__tabs hub-doc-table__tabs--scroll mb-4" aria-label="{{ __('invoices.lifecycle_tabs_label') }}" data-testid="hub-invoice-lifecycle-tabs">
        <button type="button" @class(['hub-doc-table__tab', 'hub-doc-table__tab--active' => $lifecycleTab === 'all']) wire:click="$set('lifecycleTab', 'all')">
            {{ __('invoices.lifecycle_tab_all') }}
        </button>
        <button type="button" @class(['hub-doc-table__tab', 'hub-doc-table__tab--active' => $lifecycleTab === 'drafts']) wire:click="$set('lifecycleTab', 'drafts')">
            {{ __('invoices.lifecycle_tab_drafts') }}
            @if (($draftCount ?? 0) > 0)
                <span class="hub-doc-table__tab-badge">{{ $draftCount }}</span>
            @endif
        </button>
        <button type="button" @class(['hub-doc-table__tab', 'hub-doc-table__tab--active' => $lifecycleTab === 'issued']) wire:click="$set('lifecycleTab', 'issued')">
            {{ __('invoices.lifecycle_tab_issued') }}
        </button>
        <button type="button" @class(['hub-doc-table__tab', 'hub-doc-table__tab--active' => $lifecycleTab === 'awaiting_clearance']) wire:click="$set('lifecycleTab', 'awaiting_clearance')">
            {{ __('invoices.lifecycle_tab_awaiting_clearance') }}
        </button>
        <button type="button" @class(['hub-doc-table__tab', 'hub-doc-table__tab--active' => $lifecycleTab === 'unpaid']) wire:click="$set('lifecycleTab', 'unpaid')">
            {{ __('invoices.lifecycle_tab_unpaid') }}
        </button>
    </nav>

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
        @php
            $showZatcaBulkResubmit = ($zatcaPendingResubmitCount ?? 0) > 0
                && (
                    in_array($zatcaTab, [\App\Support\Hub\HubInvoiceZatcaTab::ALL, \App\Support\Hub\HubInvoiceZatcaTab::FAILED], true)
                    || $lifecycleTab === \App\Support\Hub\HubInvoiceLifecycleTab::AWAITING_CLEARANCE
                );
        @endphp
        @if ($showZatcaBulkResubmit)
            <button
                type="button"
                class="tap-action-muted min-h-11 px-4 text-sm font-semibold @if (! ($canResubmitZatca ?? false)) opacity-60 @endif"
                @if ($canResubmitZatca ?? false)
                    wire:click="resubmitAllFailed"
                    wire:loading.attr="disabled"
                    wire:target="resubmitAllFailed"
                @else
                    disabled
                    title="{{ __('invoices.zatca_resubmit_disabled_no_zheart') }}"
                @endif
                data-testid="hub-zatca-resubmit-all-pending"
            >
                {{ __('invoices.zatca_resubmit_all_pending') }}
            </button>
        @endif
    </div>

    @if ($invoices->isNotEmpty())
        <ul class="mb-4 space-y-3 sm:hidden" data-testid="hub-invoice-cards">
            @foreach ($invoices as $row)
                @include('livewire.invoices.partials.hub-invoice-mobile-card', ['row' => $row])
            @endforeach
        </ul>
    @else
        <p class="mb-4 text-center text-sm text-[var(--app-muted)] sm:hidden">{{ __('invoices.empty') }}</p>
    @endif

    <div class="hidden sm:block">
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
                    <th scope="col" class="hub-doc-table__status-col">{{ __('invoices.col_payment_status') }}</th>
                    <th scope="col" class="hub-doc-table__status-col">{{ __('invoices.col_invoice_status') }}</th>
                    <th scope="col" class="hub-doc-table__status-col">{{ __('invoices.col_zatca_status') }}</th>
                    <th scope="col" class="text-end">{{ __('invoices.col_actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $row)
                    @php
                        $invoice = $row['invoice'];
                        $urls = $row['print_urls'];
                        $paymentKey = $row['payment_status']?->value;
                        $a = $row['actions'];
                    @endphp
                    <tr wire:key="inv-{{ $invoice->id }}" @class(['hub-doc-table__data-row' => filled($row['row_url'] ?? null)])>
                        <td class="hub-doc-table__sticky hub-doc-table__doc-number">
                            @if (filled($row['row_url'] ?? null))
                                <a href="{{ $row['row_url'] }}" wire:navigate class="hub-doc-table__row-link">{{ $row['display_number'] }}</a>
                            @else
                                {{ $row['display_number'] }}
                            @endif
                        </td>
                        <td class="tabular-nums whitespace-nowrap">{{ $invoice->issued_at?->format('Y-m-d') ?? ($invoice->created_at?->format('Y-m-d') ?? '—') }}</td>
                        <td class="hub-doc-table__customer" dir="auto" title="{{ $row['customer'] }}">
                            <span class="hub-doc-table__customer-text">{{ $row['customer'] }}</span>
                        </td>
                        <td class="hub-doc-table__amount"><x-invoices.sar-symbol /> {{ number_format($row['total_inc_vat'], 2) }}</td>
                        <td class="hub-doc-table__amount">
                            @if ($row['due_amount'] === null)
                                —
                            @else
                                <x-invoices.sar-symbol /> {{ number_format($row['due_amount'], 2) }}
                            @endif
                        </td>
                        <td class="hub-doc-table__status-col">
                            @if ($row['payment_status'] === null)
                                —
                            @else
                                <span @class([
                                    'pos-status-pill',
                                    'pos-status-pill--paid' => $paymentKey === 'paid',
                                    'pos-status-pill--warning' => $paymentKey === 'partial',
                                    'pos-status-pill--refunded' => $paymentKey === 'unpaid',
                                ])>{{ $row['payment_status']->label() }}</span>
                            @endif
                        </td>
                        <td class="hub-doc-table__status-col">
                            <x-invoices.hub-invoice-status-pill :invoice="$invoice" kind="invoice" />
                        </td>
                        <td class="hub-doc-table__status-col">
                            <div class="hub-doc-table__status-cell">
                                <x-invoices.hub-invoice-status-pill
                                    :invoice="$invoice"
                                    kind="zatca"
                                    :zatca-error="$row['zatca_error']"
                                />
                                @if (\App\Support\Hub\HubInvoiceStatusDisplay::showsClearanceHint($invoice))
                                    <p class="hub-doc-table__status-hint">{{ __('invoices.zatca_clearance_hint') }}</p>
                                @endif
                            </div>
                        </td>
                        <td>
                            <x-hub.invoice-row-actions
                                :invoice-id="$invoice->id"
                                :actions="$a"
                                :more="$row['more']"
                                :view-url="$row['view_url']"
                                :download-url="$urls['download']"
                                :print-url="$urls['print']"
                                :print-a4-url="$urls['a4'] ?? null"
                                :print-cash-url="$urls['cash'] ?? null"
                                :print-layout="$urls['layout'] ?? 'a4'"
                                :print-back-url="$urls['fallback_back'] ?? route('admin.invoices')"
                                :can-resubmit-zatca="$canResubmitZatca ?? false"
                                menu-viewport="table"
                            />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="p-6 text-center text-sm text-[var(--app-muted)]">{{ __('invoices.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </x-hub.document-table-shell>
    </div>

    <x-admin.hub-pager class="mt-4" :paginator="$invoices" />
</div>