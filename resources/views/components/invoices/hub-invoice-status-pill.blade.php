@props([
    'invoice',
    'kind',
    'zatcaError' => null,
])

@php
    use App\Support\Hub\HubInvoiceStatusDisplay;
    use App\Support\Hub\HubInvoiceZatcaBadge;

    $isZatca = $kind === 'zatca';
    $label = $isZatca
        ? HubInvoiceStatusDisplay::zatcaBadgeLabel($invoice)
        : HubInvoiceStatusDisplay::invoiceStatusLabel($invoice);
    $variant = $isZatca
        ? HubInvoiceStatusDisplay::zatcaBadgePillVariant($invoice)
        : HubInvoiceStatusDisplay::invoiceStatusPillVariant($invoice);
    $badge = HubInvoiceZatcaBadge::badgeKey($invoice);
    $tooltip = $isZatca
        ? match (true) {
            filled($zatcaError) => $zatcaError,
            $badge === HubInvoiceZatcaBadge::ClearancePending && HubInvoiceStatusDisplay::awaitingStandardClearance($invoice)
                => HubInvoiceStatusDisplay::awaitingClearanceTooltip(),
            default => null,
        }
        : null;
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