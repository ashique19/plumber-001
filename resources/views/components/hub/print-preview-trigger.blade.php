@props([
    'src',
    'a4Url' => null,
    'cashUrl' => null,
    'pdfUrl' => null,
    'canDownloadPdf' => null,
    'layout' => 'a4',
    'backUrl' => '',
    'icon',
    'label',
    'testId' => null,
    'disabled' => false,
    'tooltip' => null,
])

<button
    type="button"
    {{ $attributes->class([
        'hub-doc-table__action',
        'hub-doc-table__action--icon',
        'hub-doc-table__action--disabled' => $disabled,
    ]) }}
    data-admin-print-open
    data-print-src="{{ $src }}"
    @if ($a4Url) data-print-a4="{{ $a4Url }}" @endif
    @if ($cashUrl) data-print-cash="{{ $cashUrl }}" @endif
    @if ($pdfUrl) data-print-pdf="{{ $pdfUrl }}" @endif
    data-print-can-download="{{ ($canDownloadPdf ?? true) && filled($pdfUrl) ? '1' : '0' }}"
    data-print-layout="{{ $layout }}"
    data-print-back="{{ $backUrl }}"
    aria-label="{{ $label }}"
    title="{{ $tooltip ?? $label }}"
    @if ($disabled) disabled aria-disabled="true" @endif
    @if ($testId) data-testid="{{ $testId }}" @endif
>
    @switch ($icon)
        @case('view')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            @break
        @case('print')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
            @break
    @endswitch
    <span class="sr-only">{{ $label }}</span>
</button>