@props([
    'rows' => [],
])

@php
    $filled = array_values(array_filter(
        $rows,
        fn (array $row): bool => filled($row['value'] ?? null) || filled($row['html'] ?? null),
    ));
    $pairs = array_chunk($filled, 2);
@endphp

@if ($filled !== [])
    <table {{ $attributes->class(['invoice-print-info-grid']) }} role="presentation" width="100%">
        <colgroup>
            <col style="width:50%">
            <col style="width:50%">
        </colgroup>
        <tbody>
            @foreach ($pairs as $pair)
                <tr>
                    @foreach ($pair as $row)
                        <td class="invoice-print-info-grid__pair" width="50%" style="padding:0;vertical-align:top">
                            <table class="invoice-print-info-grid-field" role="presentation" width="100%">
                                <colgroup>
                                    <col class="invoice-print-info-grid__col-en" style="width:20%">
                                    <col class="invoice-print-info-grid__col-value" style="width:52%">
                                    <col class="invoice-print-info-grid__col-ar" style="width:32%">
                                </colgroup>
                                <tr>
                                    <td @class([
                                        'invoice-print-info-grid__en',
                                        'invoice-print-info-grid__label--nowrap' => ! empty($row['label_nowrap']),
                                    ]) width="18%"><span class="invoice-pdf-sans-label">{{ $row['en'] }}</span></td>
                                    <td @class([
                                        'invoice-print-info-grid__value',
                                        'invoice-print-info-grid__value--nowrap' => ! empty($row['nowrap']),
                                    ]) width="55%">
                                        @if (! empty($row['html']))
                                            {!! $row['html'] !!}
                                        @else
                                            <span @if (! empty($row['nowrap'])) dir="ltr" @endif>{{ $row['value'] ?? '' }}</span>
                                        @endif
                                    </td>
                                    <td @class([
                                        'invoice-print-info-grid__ar',
                                        'invoice-print-ar',
                                        'invoice-print-info-grid__label--nowrap' => ! empty($row['label_nowrap']),
                                    ]) dir="rtl" width="27%">{{ $row['ar'] }}</td>
                                </tr>
                            </table>
                        </td>
                    @endforeach
                    @if (count($pair) === 1)
                        <td class="invoice-print-info-grid__spacer" aria-hidden="true" width="50%"></td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
@endif