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

    <section class="app-card app-filter-fields mb-4 grid grid-cols-1 items-end gap-3 p-4 md:grid-cols-2">
        <x-field.search
            :label="__('quotations.search')"
            name="search"
            wire:model.live.debounce.300ms="search"
            :placeholder="__('quotations.search_hint')"
            wrapper-class="min-w-0 md:col-span-2"
        />
        <x-field.input type="date" :label="__('quotations.date_from')" name="dateFrom" wire:model.live="dateFrom" :liveValidate="false" />
        <x-field.input type="date" :label="__('quotations.date_to')" name="dateTo" wire:model.live="dateTo" :liveValidate="false" />
    </section>

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
                            <div class="hub-doc-table__actions">
                                <a class="hub-doc-table__action" href="{{ $urls['print'] }}" target="_blank" rel="noopener">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
                                    {{ __('quotations.action_print') }}
                                </a>
                                <a class="hub-doc-table__action" href="{{ $urls['download'] }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
                                    {{ __('quotations.action_download_pdf') }}
                                </a>
                                @if ($canManageQuotations ?? false)
                                    <a class="hub-doc-table__action" href="{{ route('admin.quotations.edit', $quotation) }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        {{ __('quotations.edit') }}
                                    </a>
                                    <button type="button" class="hub-doc-table__action" wire:click="convertToInvoice({{ $quotation->id }})">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M12 18v-6"/><path d="M9 15h6"/></svg>
                                        {{ __('quotations.convert') }}
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-6 text-center text-sm text-[var(--app-muted)]">{{ __('quotations.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </x-hub.document-table-shell>

    <div class="mt-4">
        {{ $quotations->links() }}
    </div>

    @if ($canManageQuotations ?? false)
        <x-admin.hub-fab :href="route('admin.quotations.create')" :label="__('quotations.create')" />
    @endif
</div>