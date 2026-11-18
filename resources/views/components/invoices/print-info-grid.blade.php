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
    <table {{ $attributes->class(['invoice-print-info-grid']) }} role="presentation">
        <colgroup>
            <col class="invoice-print-info-grid__col-en" />
            <col class="invoice-print-info-grid__col-value" />
            <col class="invoice-print-info-grid__col-ar" />
            <col class="invoice-print-info-grid__col-en" />
            <col class="invoice-print-info-grid__col-value" />
            <col class="invoice-print-info-grid__col-ar" />
        </colgroup>
        <tbody>
            @foreach ($pairs as $pair)
                <tr>
                    @foreach ($pair as $row)
                        <td @class([
                            'invoice-print-info-grid__en',
                            'invoice-print-info-grid__label--nowrap' => ! empty($row['label_nowrap']),
                        ])>{{ $row['en'] }}</td>
                        <td @class([
                            'invoice-print-info-grid__value',
                            'invoice-print-info-grid__value--nowrap' => ! empty($row['nowrap']),
                        ]) @if (! empty($row['nowrap'])) dir="ltr" @endif>
                            @if (! empty($row['html']))
                                {!! $row['html'] !!}
                            @else
                                {{ $row['value'] ?? '' }}
                            @endif
                        </td>
                        <td @class([
                            'invoice-print-info-grid__ar',
                            'invoice-print-ar',
                            'invoice-print-info-grid__label--nowrap' => ! empty($row['label_nowrap']),
                        ]) dir="rtl">{{ $row['ar'] }}</td>
                    @endforeach
                    @if (count($pair) === 1)
                        <td colspan="3" class="invoice-print-info-grid__spacer" aria-hidden="true"></td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
@endif