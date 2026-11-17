@push('head')
    @vite(['resources/css/hub-document-table.css'])
@endpush

<div class="quotation-hub admin-hub-page pb-24 lg:pb-8">
    <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="app-page-title">{{ __('quotations.title') }}</h1>
            <p class="app-page-lead">{{ __('quotations.subtitle') }}</p>
        </div>
        @if ($canManageQuotations ?? false)
            <a href="{{ route('admin.quotations.create') }}" wire:navigate class="tap-action-primary mb-0 hidden min-h-11 px-4 text-sm font-semibold sm:inline-flex">
                {{ __('quotations.create') }}
            </a>
        @endif
    </header>

    <section class="app-card app-filter-fields mb-4 grid grid-cols-1 items-end gap-3 p-4 md:grid-cols-2 lg:grid-cols-3">
        <x-field.search
            :label="__('quotations.search')"
            name="search"
            wire:model.live.debounce.300ms="search"
            :placeholder="__('quotations.search_hint')"
            wrapper-class="min-w-0 md:col-span-2 lg:col-span-1"
        />
        <x-field.input type="date" :label="__('quotations.date_from')" name="dateFrom" wire:model.live="dateFrom" :liveValidate="false" />
        <x-field.input type="date" :label="__('quotations.date_to')" name="dateTo" wire:model.live="dateTo" :liveValidate="false" />
    </section>

    @if ($quotations->isNotEmpty())
        <ul class="mb-4 space-y-3 sm:hidden" data-testid="hub-quotation-cards">
            @foreach ($quotations as $row)
                @php
                    $quotation = $row['quotation'];
                    $urls = $row['print_urls'];
                @endphp
                <li wire:key="quotation-card-{{ $quotation->id }}" class="hub-doc-mobile-card">
                    <div class="hub-doc-mobile-card__head">
                        <div>
                            <p class="font-semibold tabular-nums">{{ $quotation->quotation_number }}</p>
                            <p class="text-xs text-[var(--app-muted)]">{{ $row['customer'] }}</p>
                        </div>
                    </div>
                    <dl class="hub-doc-mobile-card__meta">
                        <div class="hub-doc-mobile-card__meta-row">
                            <dt class="text-[var(--app-muted)]">{{ __('quotations.col_valid_until') }}</dt>
                            <dd class="tabular-nums">{{ $quotation->validity_until?->format('Y-m-d') ?? '—' }}</dd>
                        </div>
                        <div class="hub-doc-mobile-card__meta-row">
                            <dt class="text-[var(--app-muted)]">{{ __('quotations.col_amount') }}</dt>
                            <dd class="tabular-nums font-medium"><x-invoices.sar-symbol /> {{ number_format($row['total_inc_vat'], 2) }}</dd>
                        </div>
                    </dl>
                    <div class="hub-doc-mobile-card__actions">
                        <x-hub.quotation-row-actions
                            :quotation-id="$quotation->id"
                            :print-url="$urls['print']"
                            :download-url="$urls['download']"
                            :print-back-url="$urls['fallback_back'] ?? route('admin.quotations')"
                            :can-manage="$canManageQuotations ?? false"
                        />
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <p class="mb-4 text-center text-sm text-[var(--app-muted)] sm:hidden">{{ __('quotations.empty') }}</p>
    @endif

    <div class="hidden sm:block">
        <x-hub.document-table-shell :aria-label="__('quotations.table_caption')">
            <thead>
                <tr>
                    <th scope="col" class="hub-doc-table__sticky">
                        <button type="button" class="hub-doc-table__sort" wire:click="sortByColumn('quotation_number')" @if ($sortBy === 'quotation_number') aria-sort="{{ $sortDir === 'asc' ? 'ascending' : 'descending' }}" @endif>
                            {{ __('quotations.col_number') }}
                        </button>
                    </th>
                    <th scope="col">
                        <button type="button" class="hub-doc-table__sort" wire:click="sortByColumn('created_at')" @if ($sortBy === 'created_at') aria-sort="{{ $sortDir === 'asc' ? 'ascending' : 'descending' }}" @endif>
                            {{ __('quotations.col_date') }}
                        </button>
                    </th>
                    <th scope="col">
                        <button type="button" class="hub-doc-table__sort" wire:click="sortByColumn('buyer_name')" @if ($sortBy === 'buyer_name') aria-sort="{{ $sortDir === 'asc' ? 'ascending' : 'descending' }}" @endif>
                            {{ __('quotations.col_customer') }}
                        </button>
                    </th>
                    <th scope="col">
                        <button type="button" class="hub-doc-table__sort" wire:click="sortByColumn('validity_until')" @if ($sortBy === 'validity_until') aria-sort="{{ $sortDir === 'asc' ? 'ascending' : 'descending' }}" @endif>
                            {{ __('quotations.col_valid_until') }}
                        </button>
                    </th>
                    <th scope="col">{{ __('quotations.col_amount') }}</th>
                    <th scope="col" class="text-end">{{ __('quotations.col_actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($quotations as $row)
                    @php
                        $quotation = $row['quotation'];
                        $urls = $row['print_urls'];
                    @endphp
                    <tr wire:key="quotation-{{ $quotation->id }}">
                        <td class="hub-doc-table__sticky font-semibold tabular-nums">{{ $quotation->quotation_number }}</td>
                        <td class="tabular-nums whitespace-nowrap">{{ $quotation->created_at?->format('Y-m-d') ?? '—' }}</td>
                        <td class="max-w-[12rem] truncate" title="{{ $row['customer'] }}">{{ $row['customer'] }}</td>
                        <td class="tabular-nums whitespace-nowrap">{{ $quotation->validity_until?->format('Y-m-d') ?? '—' }}</td>
                        <td class="hub-doc-table__amount">
                            <x-invoices.sar-symbol /> {{ number_format($row['total_inc_vat'], 2) }}
                        </td>
                        <td>
                            <x-hub.quotation-row-actions
                                :quotation-id="$quotation->id"
                                :print-url="$urls['print']"
                                :download-url="$urls['download']"
                                :print-back-url="$urls['fallback_back'] ?? route('admin.quotations')"
                                :can-manage="$canManageQuotations ?? false"
                            />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-6 text-center text-sm text-[var(--app-muted)]">{{ __('quotations.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </x-hub.document-table-shell>
    </div>

    <div class="mt-4">
        <x-admin.hub-pager :paginator="$quotations" />
    </div>

    @if ($canManageQuotations ?? false)
        <x-admin.hub-fab :href="route('admin.quotations.create')" :label="__('quotations.create')" />
    @endif
</div>