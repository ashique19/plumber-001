@push('head')
    @vite(['resources/css/admin-hub-pagination.css'])
@endpush

@props([
    'paginator',
])

@php
    $rtl = config('locales.supported.'.app()->getLocale().'.dir', 'ltr') === 'rtl';
    $pageName = method_exists($paginator, 'getPageName') ? $paginator->getPageName() : 'page';
@endphp

<div {{ $attributes->class(['admin-hub-pager']) }} data-testid="admin-hub-pager">
    <label class="admin-hub-pager__per-page">
        <span>{{ __('pagination.per_page') }}</span>
        <select wire:model.live="perPage" aria-label="{{ __('pagination.per_page') }}">
            @foreach ($this->adminPerPageOptions() as $size)
                <option value="{{ $size }}">{{ $size }}</option>
            @endforeach
        </select>
    </label>

    @if ($paginator->total() > 0)
        <nav class="admin-hub-pager__nav" aria-label="{{ __('pagination.compact_nav_label') }}" @if ($rtl) dir="rtl" @endif>
            <button
                type="button"
                class="admin-hub-pager__btn"
                wire:click="previousPage('{{ $pageName }}')"
                wire:loading.attr="disabled"
                @disabled($paginator->onFirstPage())
                data-testid="admin-hub-pager-prev"
            >
                <span class="admin-hub-pager__chev admin-hub-pager__chev--prev" aria-hidden="true">←</span>
                <span>{{ __('pagination.previous') }}</span>
            </button>
            <span class="admin-hub-pager__status" data-testid="admin-hub-pager-status">
                {{ __('pagination.page_x_of_y', ['current' => $paginator->currentPage(), 'last' => max(1, $paginator->lastPage())]) }}
            </span>
            <button
                type="button"
                class="admin-hub-pager__btn"
                wire:click="nextPage('{{ $pageName }}')"
                wire:loading.attr="disabled"
                @disabled(! $paginator->hasMorePages())
                data-testid="admin-hub-pager-next"
            >
                <span>{{ __('pagination.next') }}</span>
                <span class="admin-hub-pager__chev admin-hub-pager__chev--next" aria-hidden="true">→</span>
            </button>
        </nav>
    @endif
</div>