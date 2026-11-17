@props([
    'invoice',
    'kind',
    'zatcaError' => null,
])

@php
    use App\Support\Hub\HubInvoiceStatusDisplay;
    use App\Support\Hub\HubInvoiceZatcaBadge;
    use App\Support\Hub\HubInvoiceTableRow;

    $isZatca = $kind === 'zatca';
    $label = $isZatca
        ? HubInvoiceStatusDisplay::zatcaBadgeLabel($invoice)
        : HubInvoiceStatusDisplay::invoiceStatusLabel($invoice);
    $variant = $isZatca
        ? HubInvoiceStatusDisplay::zatcaBadgePillVariant($invoice)
        : HubInvoiceStatusDisplay::invoiceStatusPillVariant($invoice);
    $showClearanceHint = HubInvoiceStatusDisplay::showsClearanceHint($invoice)
        && (
            (! $isZatca && HubInvoiceTableRow::invoiceStatusKey($invoice) === 'pending')
            || ($isZatca && HubInvoiceZatcaBadge::badgeKey($invoice) === HubInvoiceZatcaBadge::Pending)
        );
    $awaitingClearance = HubInvoiceStatusDisplay::awaitingStandardClearance($invoice)
        && (
            (! $isZatca && HubInvoiceTableRow::invoiceStatusKey($invoice) === 'pending')
            || ($isZatca && HubInvoiceZatcaBadge::badgeKey($invoice) === HubInvoiceZatcaBadge::Pending)
        );
    $tooltip = $awaitingClearance
        ? HubInvoiceStatusDisplay::awaitingClearanceTooltip()
        : ($showClearanceHint
            ? HubInvoiceStatusDisplay::clearanceHint()
            : ($isZatca && filled($zatcaError) ? $zatcaError : null));
@endphp

<span
    {{ $attributes->class([
        'pos-status-pill',
        'pos-status-pill--'.$variant,
        'hub-invoice-status-pill',
        'hub-invoice-status-pill--'.$kind,
    ]) }}
    @if ($tooltip) title="{{ $tooltip }}" @endif
    data-testid="hub-invoice-status-pill-{{ $kind }}"
>{{ $label }}</span>