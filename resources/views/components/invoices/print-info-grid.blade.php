@props([
    'rows' => [],
])

@php
    use App\Support\Invoicing\InvoicePrintInfoGrid;

    $filled = array_values(array_filter(
        $rows,
        fn (array $row): bool => filled($row['value'] ?? null) || filled($row['html'] ?? null),
    ));
    $pairs = array_chunk($filled, 2);
    $enWidth = InvoicePrintInfoGrid::COL_EN_PCT;
    $valueWidth = InvoicePrintInfoGrid::COL_VALUE_PCT;
    $arWidth = InvoicePrintInfoGrid::COL_AR_PCT;
@endphp

@if ($filled !== [])
    <table {{ $attributes->class(['invoice-print-info-grid']) }} role="presentation" width="100%">
        <colgroup>
            <col class="invoice-print-info-grid__col-en" style="width:{{ $enWidth }}%">
            <col class="invoice-print-info-grid__col-value" style="width:{{ $valueWidth }}%">
            <col class="invoice-print-info-grid__col-ar" style="width:{{ $arWidth }}%">
            <col class="invoice-print-info-grid__col-en" style="width:{{ $enWidth }}%">
            <col class="invoice-print-info-grid__col-value" style="width:{{ $valueWidth }}%">
            <col class="invoice-print-info-grid__col-ar" style="width:{{ $arWidth }}%">
        </colgroup>
        <tbody>
            @foreach ($pairs as $pair)
                <tr>
                    @foreach ($pair as $row)
                        <td @class([
                            'invoice-print-info-grid__en',
                            'invoice-print-info-grid__label--nowrap' => ! empty($row['label_nowrap']),
                        ]) width="{{ $enWidth }}%"><span class="invoice-pdf-sans-label">{{ $row['en'] }}</span></td>
                        <td @class([
                            'invoice-print-info-grid__value',
                            'invoice-print-info-grid__value--nowrap' => ! empty($row['nowrap']),
                        ]) width="{{ $valueWidth }}%">
                            @if (! empty($row['html']))
                                {!! $row['html'] !!}
                            @else
                                <span @class(['invoice-pdf-sans-value' => true]) @if (! empty($row['nowrap'])) dir="ltr" @endif>{{ $row['value'] ?? '' }}</span>
                            @endif
                        </td>
                        <td @class([
                            'invoice-print-info-grid__ar',
                            'invoice-print-ar',
                            'invoice-print-info-grid__label--nowrap' => ! empty($row['label_nowrap']),
                        ]) dir="rtl" width="{{ $arWidth }}%">{{ $row['ar'] }}</td>
                    @endforeach
                    @if (count($pair) === 1)
                        <td class="invoice-print-info-grid__empty" colspan="3" aria-hidden="true"></td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
